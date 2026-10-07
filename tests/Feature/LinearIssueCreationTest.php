<?php

use App\Enums\Level;
use App\Enums\LinearLinkSource;
use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\TicketLinearLink;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();

    config(['services.linear.key' => 'lin_api_test_key', 'services.linear.team_id' => 'team-ges']);
});

/**
 * The requests sent to Linear for one GraphQL operation.
 *
 * @return list<Request>
 */
function linearRequests(string $operation): array
{
    return Http::recorded(fn (Request $request) => linearOperation($request) === $operation)
        ->map(fn (array $pair) => $pair[0])
        ->values()
        ->all();
}

test('an admin creates a linear issue for a ticket from their list and it ends up linked both ways', function () {
    fakeLinearOperations();
    $admin = User::factory()->admin()->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);
    $ticket = Ticket::factory()->create([
        'title' => 'No anda la facturación',
        'description' => '<p>Falla al emitir.</p>',
        'status' => 'open',
        'assigned_to' => $admin->id,
        'importance' => Level::High,
        'urgency' => Level::High,
    ]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-list')
        ->call('createLinearIssue', $ticket->id)
        ->assertDispatched('toast-show', fn (string $name, array $params) => $params['slots']['text'] === __('Se creó :identifier en Linear.', ['identifier' => 'GES-950']));

    [$create] = linearRequests('CreateIssue');
    $input = $create['variables']['input'];

    expect($input)
        ->teamId->toBe('team-ges')
        ->title->toBe("[TK-{$ticket->id}] No anda la facturación")
        ->priority->toBe(1)
        ->assigneeId->toBe('linear-user-ana')
        ->and(Str::isUuid($input['id']))->toBeTrue()
        ->and($input['description'])->toContain($ticket->canonicalUrl(), 'Ana Pérez', 'Falla al emitir.');

    expect(linearRequests('UserByEmail')[0]['variables'])->toBe(['email' => 'ana@example.com'])
        ->and(linearRequests('AttachUrl')[0]['variables'])->toMatchArray(['issueId' => 'uuid-ges-950', 'url' => $ticket->canonicalUrl()]);

    expect($ticket->linearLinks()->sole())
        ->identifier->toBe('GES-950')
        ->source->toBe(LinearLinkSource::Attachment)
        ->linked_by->toBe($admin->id);
});

test('the issue description keeps the ticket text readable as plain text', function () {
    fakeLinearOperations();
    $ticket = Ticket::factory()->create([
        'status' => 'open',
        'description' => '<p>Hola &amp; chau</p><ul><li>uno</li><li>dos</li></ul><p>Ver <a href="https://example.com/doc">la doc</a></p>',
    ]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.ticket-list')->call('createLinearIssue', $ticket->id);

    expect(linearRequests('CreateIssue')[0]['variables']['input']['description'])
        ->toEndWith("---\n\n```\nHola & chau\n- uno\n- dos\nVer la doc (https://example.com/doc)\n```");
});

test('the issue description keeps markdown from the ticket text inert inside a code block', function () {
    fakeLinearOperations();
    $ticket = Ticket::factory()->create([
        'status' => 'open',
        'description' => '<p>![x](https://tracker.example/p.png)</p><p>[click](https://phish.example)</p>',
    ]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.ticket-list')->call('createLinearIssue', $ticket->id);

    expect(linearRequests('CreateIssue')[0]['variables']['input']['description'])
        ->toEndWith("---\n\n```\n![x](https://tracker.example/p.png)\n[click](https://phish.example)\n```");
});

test('the issue description fence is longer than any backtick run in the ticket text', function () {
    fakeLinearOperations();
    $ticket = Ticket::factory()->create([
        'status' => 'open',
        'description' => '<p>antes</p><p>```</p><p>![x](https://tracker.example/p.png)</p>',
    ]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.ticket-list')->call('createLinearIssue', $ticket->id);

    expect(linearRequests('CreateIssue')[0]['variables']['input']['description'])
        ->toEndWith("---\n\n````\nantes\n```\n![x](https://tracker.example/p.png)\n````");
});

test('the ticket priority maps onto linear priorities', function (Level $importance, Level $urgency, int $linearPriority) {
    fakeLinearOperations();
    $ticket = Ticket::factory()->create(['status' => 'open', 'importance' => $importance, 'urgency' => $urgency]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.ticket-list')->call('createLinearIssue', $ticket->id);

    expect(linearRequests('CreateIssue')[0]['variables']['input']['priority'])->toBe($linearPriority);
})->with([
    'critical' => [Level::High, Level::High, 1],
    'high' => [Level::High, Level::Medium, 2],
    'medium' => [Level::Medium, Level::Medium, 3],
    'low' => [Level::Low, Level::Low, 4],
]);

test('an admin without a linear user gets an unassigned issue', function () {
    fakeLinearOperations(['UserByEmail' => ['users' => ['nodes' => []]]]);
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.ticket-list')->call('createLinearIssue', $ticket->id);

    expect(linearRequests('CreateIssue')[0]['variables']['input']['assigneeId'])->toBeNull()
        ->and($ticket->linearLinks()->exists())->toBeTrue();
});

test('when attaching the ticket url fails the issue is still linked, as a manual link', function () {
    fakeLinearOperations(['AttachUrl' => Http::response('Bad gateway', 502)]);
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.ticket-list')->call('createLinearIssue', $ticket->id);

    expect($ticket->linearLinks()->sole())
        ->identifier->toBe('GES-950')
        ->source->toBe(LinearLinkSource::Manual);
});

test('an issue created behind a failed response is found by its id instead of created twice', function () {
    fakeLinearOperations([
        'CreateIssue' => Http::response('Gateway timeout', 504),
        'Issues' => fn (Request $request) => ['issues' => ['nodes' => [
            [...linearIssueNode('GES-950'), 'id' => $request['variables']['filter']['id']['in'][0]],
        ]]],
    ]);
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.ticket-list')->call('createLinearIssue', $ticket->id);

    expect(linearRequests('CreateIssue'))->toHaveCount(1)
        ->and(linearRequests('Issues')[0]['variables']['filter']['id']['in'])->toBe([linearRequests('CreateIssue')[0]['variables']['input']['id']])
        ->and($ticket->linearLinks()->sole()->identifier)->toBe('GES-950');
});

test('a retry after an unconfirmed creation reuses the issue id instead of creating a second issue', function () {
    $linearCreatedIt = false;
    fakeLinearOperations([
        'CreateIssue' => function () use (&$linearCreatedIt) {
            return $linearCreatedIt
                ? Http::response(['errors' => [['message' => 'Entity already exists']], 'data' => null])
                : Http::response('Gateway timeout', 504);
        },
        'Issues' => function (Request $request) use (&$linearCreatedIt) {
            return ['issues' => ['nodes' => $linearCreatedIt
                ? [[...linearIssueNode('GES-950'), 'id' => $request['variables']['filter']['id']['in'][0]]]
                : [],
            ]];
        },
    ]);
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.ticket-list')
        ->call('createLinearIssue', $ticket->id)
        ->assertDispatched('toast-show', fn (string $name, array $params) => $params['dataset']['variant'] === 'danger');

    expect($ticket->linearLinks()->exists())->toBeFalse();

    $linearCreatedIt = true;

    Livewire::test('tickets.ticket-list')->call('createLinearIssue', $ticket->id);

    [$first, $retry] = linearRequests('CreateIssue');
    $sentIds = collect([...linearRequests('CreateIssue'), ...linearRequests('Issues')])
        ->map(fn (Request $request) => $request['variables']['input']['id'] ?? $request['variables']['filter']['id']['in'][0])
        ->unique();

    expect($retry['variables']['input']['id'])->toBe($first['variables']['input']['id'])
        ->and($sentIds->all())->toBe([$first['variables']['input']['id']])
        ->and($ticket->linearLinks()->sole()->identifier)->toBe('GES-950');
});

test('the issue id kept for retries is forgotten once the issue is linked', function () {
    fakeLinearOperations();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.ticket-list')->call('createLinearIssue', $ticket->id);

    expect($ticket->linearLinks()->exists())->toBeTrue()
        ->and(Cache::has("linear-issue-create:{$ticket->id}:issue-id"))->toBeFalse();
});

test('when linear rejects the issue the admin is told and nothing is linked', function () {
    fakeLinearOperations(['CreateIssue' => Http::response(['errors' => [['message' => 'Forbidden']], 'data' => null])]);
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.ticket-list')
        ->call('createLinearIssue', $ticket->id)
        ->assertDispatched('toast-show', fn (string $name, array $params) => $params['dataset']['variant'] === 'danger');

    expect($ticket->linearLinks()->exists())->toBeFalse();
});

test('a ticket that is already linked creates no issue', function () {
    fakeLinearOperations();
    $ticket = Ticket::factory()->create(['status' => 'open']);
    TicketLinearLink::factory()->for($ticket)->create(['identifier' => 'GES-911']);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.ticket-list')
        ->call('createLinearIssue', $ticket->id)
        ->assertDispatched('toast-show', fn (string $name, array $params) => $params['slots']['text'] === __('El ticket ya estaba vinculado a :identifier.', ['identifier' => 'GES-911']));

    Http::assertNothingSent();
});

test('a second creation while one is in progress creates no issue', function () {
    fakeLinearOperations();
    $ticket = Ticket::factory()->create(['status' => 'open']);
    Cache::lock("linear-issue-create:{$ticket->id}", 30)->acquire();

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.ticket-list')
        ->call('createLinearIssue', $ticket->id)
        ->assertDispatched('toast-show', fn (string $name, array $params) => $params['dataset']['variant'] === 'danger');

    Http::assertNothingSent();
});

test('only admins can create a linear issue, and only for a ticket that passed triage', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->create();

    expect($admin->can('createLinearIssue', Ticket::factory()->create(['status' => 'open'])))->toBeTrue()
        ->and($client->can('createLinearIssue', Ticket::factory()->for($client)->create(['status' => 'open'])))->toBeFalse()
        ->and($admin->can('createLinearIssue', Ticket::factory()->draft()->for($admin)->create()))->toBeFalse()
        ->and($admin->can('createLinearIssue', Ticket::factory()->create(['status' => 'open', 'triage_status' => TriageStatus::Pending])))->toBeFalse()
        ->and($admin->can('createLinearIssue', Ticket::factory()->create(['status' => 'open', 'triage_status' => TriageStatus::Rejected])))->toBeFalse();
});

test('a client cannot create a linear issue for their own ticket', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create(['status' => 'open']);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->call('createLinearIssue', $ticket->id)
        ->assertForbidden();

    Http::assertNothingSent();
});

test('in their list an admin sees the create button on unlinked tickets and the linear state on linked ones', function () {
    $admin = User::factory()->admin()->create();
    $unlinked = Ticket::factory()->create(['status' => 'open', 'assigned_to' => $admin->id]);
    $linked = Ticket::factory()->create(['status' => 'open', 'assigned_to' => $admin->id]);
    TicketLinearLink::factory()->for($linked)->create(['identifier' => 'GES-911', 'state_name' => 'In Review']);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-list')
        ->call('selectTab', 'assigned')
        ->assertSeeHtml("createLinearIssue({$unlinked->id})")
        ->assertDontSeeHtml("createLinearIssue({$linked->id})")
        ->assertSee('GES-911 · In Review');

    Http::assertNothingSent();
});

test('the create button is hidden without a linear team to create issues in', function () {
    config(['services.linear.team_id' => null]);
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open', 'assigned_to' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-list')
        ->call('selectTab', 'assigned')
        ->assertSee($ticket->title)
        ->assertDontSeeHtml('createLinearIssue(');
});

test('the linear panel of an unlinked ticket creates its issue', function () {
    fakeLinearOperations();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.linear-links', ['ticket' => $ticket])
        ->assertSee(__('Crear issue en Linear'))
        ->call('createIssue')
        ->assertSee('GES-950')
        ->assertDontSee(__('Crear issue en Linear'));

    expect($ticket->linearLinks()->sole()->source)->toBe(LinearLinkSource::Attachment);
});

test('the linear panel does not offer to create an issue for a ticket pending triage', function () {
    fakeLinearOperations();
    $ticket = Ticket::factory()->create(['status' => 'open', 'triage_status' => TriageStatus::Pending]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.linear-links', ['ticket' => $ticket])
        ->assertDontSee(__('Crear issue en Linear'))
        ->call('createIssue')
        ->assertForbidden();
});
