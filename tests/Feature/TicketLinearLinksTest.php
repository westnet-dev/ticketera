<?php

use App\Enums\LinearLinkSource;
use App\Models\Ticket;
use App\Models\TicketLinearLink;
use App\Models\User;
use App\Services\Linear\LinearIssue;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();

    config(['services.linear.key' => 'lin_api_test_key']);
});

test('only admins can manage the linear links of a submitted ticket', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create(['status' => 'open']);
    $draft = Ticket::factory()->draft()->for($admin)->create();

    expect($admin->can('manageLinearLinks', $ticket))->toBeTrue()
        ->and($client->can('manageLinearLinks', $ticket))->toBeFalse()
        ->and($admin->can('manageLinearLinks', $draft))->toBeFalse();
});

test('linking a linear issue stores its cached fields and who linked it', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $link = $ticket->linkLinearIssue(LinearIssue::fromNode(linearIssueNode()), LinearLinkSource::Manual, $admin->id);

    expect($link->fresh())
        ->identifier->toBe('GES-911')
        ->state_name->toBe('In Progress')
        ->assignee_name->toBe('Ana Pérez')
        ->source->toBe(LinearLinkSource::Manual)
        ->linked_by->toBe($admin->id);
});

test('linking the same issue again refreshes it without changing how it was linked', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);
    $ticket->linkLinearIssue(LinearIssue::fromNode(linearIssueNode()), LinearLinkSource::Manual, $admin->id);

    $ticket->linkLinearIssue(LinearIssue::fromNode(linearIssueNode(stateName: 'Done', stateType: 'completed')), LinearLinkSource::Attachment);

    expect(TicketLinearLink::sole())
        ->state_name->toBe('Done')
        ->source->toBe(LinearLinkSource::Manual)
        ->linked_by->toBe($admin->id);
});

test('the ticket page shows the linear panel to admins without calling linear while it loads', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($admin)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSee(__('Consultando Linear...'));

    Http::assertNothingSent();
});

test('the ticket page hides the linear panel from clients and when no key is configured', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create(['status' => 'open']);

    $this->actingAs($client)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertDontSee(__('Consultando Linear...'));

    config(['services.linear.key' => null]);

    $this->actingAs($admin)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertDontSee(__('Consultando Linear...'));
});

test('a client cannot load the linear panel', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create(['status' => 'open']);

    $this->actingAs($client);

    Livewire::test('tickets.linear-links', ['ticket' => $ticket])
        ->assertForbidden();
});

test('an admin links an issue by its identifier or its url', function (string $reference) {
    fakeLinear(issueNodes: [linearIssueNode()]);
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($admin);

    Livewire::test('tickets.linear-links', ['ticket' => $ticket])
        ->set('reference', $reference)
        ->call('link')
        ->assertHasNoErrors()
        ->assertSet('reference', '')
        ->assertSee('GES-911');

    expect($ticket->linearLinks()->sole())
        ->source->toBe(LinearLinkSource::Manual)
        ->linked_by->toBe($admin->id);
})->with([
    'identifier' => 'GES-911',
    'url' => 'https://linear.app/acme/issue/GES-911/vincular-tickets-con-linear',
]);

test('linking fails with a message when the reference is not an issue, does not exist or linear is down', function (string $reference, Closure $fake) {
    $fake();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($admin);

    Livewire::test('tickets.linear-links', ['ticket' => $ticket])
        ->set('reference', $reference)
        ->call('link')
        ->assertHasErrors('reference');

    expect($ticket->linearLinks()->exists())->toBeFalse();
})->with([
    'free text' => ['el ticket de facturación', fn () => fakeLinear()],
    'unknown issue' => ['GES-99999', fn () => fakeLinear()],
    'linear down' => ['GES-911', fn () => Http::fake(['api.linear.app/*' => Http::response('Bad gateway', 502)])],
]);

test('opening the ticket links the issues that attach its url in linear', function () {
    fakeLinear(attachedNodes: [linearIssueNode('GES-120')]);
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($admin);

    Livewire::test('tickets.linear-links', ['ticket' => $ticket])
        ->assertSee('GES-120')
        ->assertSee(__('Vinculado desde Linear'));

    expect($ticket->linearLinks()->sole()->source)->toBe(LinearLinkSource::Attachment);

    Http::assertSent(fn (Request $request) => ($request['variables']['url'] ?? null) === $ticket->canonicalUrl());
});

test('opening the ticket refreshes the cached state of its links, even for an issue moved to another team', function () {
    $ticket = Ticket::factory()->create(['status' => 'open']);
    $link = TicketLinearLink::factory()->for($ticket)->create([
        'linear_issue_id' => 'uuid-ges-911',
        'identifier' => 'GES-911',
        'state_name' => 'Todo',
        'state_type' => 'unstarted',
    ]);
    fakeLinear(issueNodes: [[...linearIssueNode('OPS-12', 'Done', 'completed'), 'id' => 'uuid-ges-911']]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.linear-links', ['ticket' => $ticket]);

    expect($link->fresh())
        ->identifier->toBe('OPS-12')
        ->state_name->toBe('Done')
        ->state_type->toBe('completed');
});

test('reopening the ticket within the sync interval does not call linear again', function () {
    fakeLinear();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.linear-links', ['ticket' => $ticket]);
    Livewire::test('tickets.linear-links', ['ticket' => $ticket]);

    Http::assertSentCount(1);
});

test('when linear is down the panel keeps the last known state and retries on the next view', function () {
    Http::fake(['api.linear.app/*' => Http::failedConnection()]);
    $ticket = Ticket::factory()->create(['status' => 'open']);
    TicketLinearLink::factory()->for($ticket)->create(['identifier' => 'GES-911']);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.linear-links', ['ticket' => $ticket])
        ->assertSet('linearUnavailable', true)
        ->assertSee(__('No se pudo consultar Linear. Se muestra el último estado conocido.'))
        ->assertSee('GES-911');

    Livewire::test('tickets.linear-links', ['ticket' => $ticket]);

    Http::assertSentCount(2);
});

test('an admin can remove a manual link but not one detected from linear', function () {
    fakeLinear();
    $ticket = Ticket::factory()->create(['status' => 'open']);
    $manual = TicketLinearLink::factory()->for($ticket)->create();
    $detected = TicketLinearLink::factory()->detected()->for($ticket)->create();

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.linear-links', ['ticket' => $ticket])
        ->call('unlink', $manual->id)
        ->call('unlink', $detected->id);

    expect($ticket->linearLinks()->pluck('id')->all())->toBe([$detected->id]);
});

test('the admin backlog shows the cached linear state of each ticket without calling linear', function () {
    $ticket = Ticket::factory()->create(['status' => 'open']);
    TicketLinearLink::factory()->for($ticket)->create(['identifier' => 'GES-911', 'state_name' => 'In Review']);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.admin-ticket-list')
        ->assertSee('GES-911 · In Review');

    Http::assertNothingSent();
});

test('clients never see linear links in their ticket list', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create(['status' => 'open']);
    TicketLinearLink::factory()->for($ticket)->create(['identifier' => 'GES-911']);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->assertSee($ticket->title)
        ->assertDontSee('GES-911');
});

test('the panel offers the exact ticket url to attach in linear and shows how fresh each link is', function () {
    fakeLinear();
    $ticket = Ticket::factory()->create(['status' => 'open']);
    TicketLinearLink::factory()->for($ticket)->create(['synced_at' => now()->subHours(3)]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('tickets.linear-links', ['ticket' => $ticket])
        ->assertSeeHtml('value="'.$ticket->canonicalUrl().'"')
        ->assertSee(__('Actualizado :time', ['time' => now()->subHours(3)->diffForHumans()]));
});
