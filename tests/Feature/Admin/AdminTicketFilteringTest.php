<?php

use App\Enums\Difficulty;
use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * @return array<int, int>
 */
function listedTicketIds(Testable $component): array
{
    return $component->viewData('tickets')->pluck('id')->all();
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('searching by a word in the title narrows the listing', function () {
    $match = Ticket::factory()->create(['title' => 'No funciona la impresora de Administración', 'status' => 'open']);
    $other = Ticket::factory()->create(['title' => 'Pedido de alta de usuario', 'status' => 'open']);

    $ids = listedTicketIds(
        Livewire::test('tickets.admin-ticket-list')->set('search', 'impresora')
    );

    expect($ids)->toContain($match->id)
        ->and($ids)->not->toContain($other->id);
});

test('searching by title ignores case', function () {
    $ticket = Ticket::factory()->create(['title' => 'Falla el SERVIDOR de correo', 'status' => 'open']);

    $ids = listedTicketIds(
        Livewire::test('tickets.admin-ticket-list')->set('search', 'servidor')
    );

    expect($ids)->toContain($ticket->id);
});

test('searching by ticket number returns only that ticket', function (string $format) {
    $target = Ticket::factory()->create(['status' => 'open']);
    Ticket::factory()->count(3)->create(['status' => 'open']);

    $ids = listedTicketIds(
        Livewire::test('tickets.admin-ticket-list')
            ->set('search', str_replace(':id', (string) $target->id, $format))
    );

    expect($ids)->toBe([$target->id]);
})->with([
    'plain' => [':id'],
    'prefixed' => ['TK-:id'],
    'hashed' => ['#TK-:id'],
]);

test('a search with no matches shows the empty state instead of the table', function () {
    Ticket::factory()->create(['title' => 'Algo totalmente distinto', 'status' => 'open']);

    Livewire::test('tickets.admin-ticket-list')
        ->set('search', 'zzzzz-no-existe')
        ->assertSee(__('Ningún ticket coincide con los filtros aplicados.'));
});

test('the search never reaches drafts or tickets pending triage', function () {
    $draft = Ticket::factory()->draft()->create(['title' => 'Borrador sobre la impresora']);
    $pending = Ticket::factory()->create([
        'title' => 'Pendiente sobre la impresora',
        'triage_status' => TriageStatus::Pending,
        'status' => 'open',
    ]);

    $ids = listedTicketIds(
        Livewire::test('tickets.admin-ticket-list')->set('search', 'impresora')
    );

    expect($ids)->not->toContain($draft->id)
        ->and($ids)->not->toContain($pending->id);
});

test('filtering by client narrows the listing to that author', function () {
    $client = User::factory()->create();
    $mine = Ticket::factory()->create(['user_id' => $client->id, 'status' => 'open']);
    $theirs = Ticket::factory()->create(['status' => 'open']);

    $ids = listedTicketIds(
        Livewire::test('tickets.admin-ticket-list')->set('clientFilter', (string) $client->id)
    );

    expect($ids)->toBe([$mine->id])
        ->and($ids)->not->toContain($theirs->id);
});

test('the client selector only offers users with a visible ticket', function () {
    $withTicket = User::factory()->create();
    Ticket::factory()->create(['user_id' => $withTicket->id, 'status' => 'open']);

    $withoutTickets = User::factory()->create();

    $onlyDrafts = User::factory()->create();
    Ticket::factory()->draft()->create(['user_id' => $onlyDrafts->id]);

    $clientIds = Livewire::test('tickets.admin-ticket-list')->viewData('clients')->pluck('id')->all();

    expect($clientIds)->toContain($withTicket->id)
        ->and($clientIds)->not->toContain($withoutTickets->id)
        ->and($clientIds)->not->toContain($onlyDrafts->id);
});

test('filtering by assignee narrows the listing to that user', function () {
    $assignee = User::factory()->admin()->create();
    $assigned = Ticket::factory()->create(['assigned_to' => $assignee->id, 'status' => 'open']);
    $unassigned = Ticket::factory()->create(['status' => 'open']);

    $ids = listedTicketIds(
        Livewire::test('tickets.admin-ticket-list')->set('assignedToFilter', (string) $assignee->id)
    );

    expect($ids)->toBe([$assigned->id])
        ->and($ids)->not->toContain($unassigned->id);
});

test('filtering by unassigned returns only tickets with nobody on them', function () {
    $assignee = User::factory()->admin()->create();
    $assigned = Ticket::factory()->create(['assigned_to' => $assignee->id, 'status' => 'open']);
    $unassigned = Ticket::factory()->create(['status' => 'open']);

    $ids = listedTicketIds(
        Livewire::test('tickets.admin-ticket-list')->set('assignedToFilter', 'unassigned')
    );

    expect($ids)->toContain($unassigned->id)
        ->and($ids)->not->toContain($assigned->id);
});

test('two filters at once return the intersection, not the union', function () {
    $client = User::factory()->create();
    $assignee = User::factory()->admin()->create();

    $both = Ticket::factory()->create([
        'user_id' => $client->id,
        'assigned_to' => $assignee->id,
        'status' => 'open',
    ]);
    $onlyClient = Ticket::factory()->create(['user_id' => $client->id, 'status' => 'open']);
    $onlyAssignee = Ticket::factory()->create(['assigned_to' => $assignee->id, 'status' => 'open']);

    $ids = listedTicketIds(
        Livewire::test('tickets.admin-ticket-list')
            ->set('clientFilter', (string) $client->id)
            ->set('assignedToFilter', (string) $assignee->id)
    );

    expect($ids)->toBe([$both->id])
        ->and($ids)->not->toContain($onlyClient->id)
        ->and($ids)->not->toContain($onlyAssignee->id);
});

test('a filter applies inside the active status tab', function () {
    $client = User::factory()->create();
    $resolved = Ticket::factory()->create(['user_id' => $client->id, 'status' => 'resolved']);
    $open = Ticket::factory()->create(['user_id' => $client->id, 'status' => 'open']);

    $ids = listedTicketIds(
        Livewire::test('tickets.admin-ticket-list')
            ->call('filterByStatus', 'resolved')
            ->set('clientFilter', (string) $client->id)
    );

    expect($ids)->toBe([$resolved->id])
        ->and($ids)->not->toContain($open->id);
});

test('the tab counts ignore the filters', function () {
    Ticket::factory()->count(3)->create(['status' => 'open']);
    $client = User::factory()->create();
    Ticket::factory()->create(['user_id' => $client->id, 'status' => 'open']);

    $component = Livewire::test('tickets.admin-ticket-list');
    $before = $component->viewData('tabCounts')['all'];

    $after = $component->set('clientFilter', (string) $client->id)->viewData('tabCounts')['all'];

    expect($after)->toBe($before);
});

test('sorting by id orders the listing numerically', function () {
    Ticket::factory()->count(3)->create(['status' => 'open']);

    $ascending = listedTicketIds(
        Livewire::test('tickets.admin-ticket-list')->call('sort', 'id')
    );

    expect($ascending)->toBe(collect($ascending)->sort()->values()->all());
});

test('choosing the same column again flips the direction', function () {
    Ticket::factory()->count(3)->create(['status' => 'open']);

    $component = Livewire::test('tickets.admin-ticket-list')->call('sort', 'id');
    expect($component->get('sortDirection'))->toBe('asc');

    $component->call('sort', 'id');
    expect($component->get('sortDirection'))->toBe('desc');

    $descending = listedTicketIds($component);
    expect($descending)->toBe(collect($descending)->sortDesc()->values()->all());
});

test('choosing a different column starts ascending', function () {
    Ticket::factory()->count(2)->create(['status' => 'open']);

    $component = Livewire::test('tickets.admin-ticket-list')
        ->call('sort', 'id')
        ->call('sort', 'id')
        ->call('sort', 'status');

    expect($component->get('sortBy'))->toBe('status')
        ->and($component->get('sortDirection'))->toBe('asc');
});

test('sorting by status follows the ticket flow, not the alphabet', function () {
    $open = Ticket::factory()->create(['status' => 'open']);
    $inProgress = Ticket::factory()->create(['status' => 'in_progress']);
    $paused = Ticket::factory()->create(['status' => 'paused']);
    $awaitingResponse = Ticket::factory()->awaitingResponse()->create();
    $cancelled = Ticket::factory()->create(['status' => 'cancelled']);

    $ids = listedTicketIds(
        Livewire::test('tickets.admin-ticket-list')->call('sort', 'status')
    );

    expect($ids)->toBe([$open->id, $inProgress->id, $paused->id, $awaitingResponse->id, $cancelled->id]);
});

test('sorting by difficulty keeps unestimated tickets last in both directions', function () {
    $hard = Ticket::factory()->withDifficulty(Difficulty::Eight)->create(['status' => 'open']);
    $easy = Ticket::factory()->withDifficulty(Difficulty::Two)->create(['status' => 'open']);
    $unestimated = Ticket::factory()->create(['status' => 'open']);

    $component = Livewire::test('tickets.admin-ticket-list')->call('sort', 'difficulty');
    expect(listedTicketIds($component))->toBe([$easy->id, $hard->id, $unestimated->id]);

    $component->call('sort', 'difficulty');
    expect(listedTicketIds($component))->toBe([$hard->id, $easy->id, $unestimated->id]);
});

test('sorting by category uses the category name', function () {
    $zeta = TicketCategory::factory()->create(['name' => 'Zeta']);
    $alpha = TicketCategory::factory()->create(['name' => 'Alpha']);

    $last = Ticket::factory()->create(['category_id' => $zeta->id, 'status' => 'open']);
    $first = Ticket::factory()->create(['category_id' => $alpha->id, 'status' => 'open']);

    $ids = listedTicketIds(
        Livewire::test('tickets.admin-ticket-list')->call('sort', 'category')
    );

    expect($ids)->toBe([$first->id, $last->id]);
});

test('uncategorised tickets stay last in both directions', function () {
    $category = TicketCategory::factory()->create(['name' => 'Alpha']);
    $categorised = Ticket::factory()->create(['category_id' => $category->id, 'status' => 'open']);
    $uncategorised = Ticket::factory()->create(['category_id' => null, 'status' => 'open']);

    $component = Livewire::test('tickets.admin-ticket-list')->call('sort', 'category');
    expect(listedTicketIds($component))->toBe([$categorised->id, $uncategorised->id]);

    $component->call('sort', 'category');
    expect(listedTicketIds($component))->toBe([$categorised->id, $uncategorised->id]);
});

test('sorting by category lists ticket ids, not category ids', function () {
    $category = TicketCategory::factory()->create(['name' => 'Alpha']);
    $ticket = Ticket::factory()->create(['category_id' => $category->id, 'status' => 'open']);

    $ids = listedTicketIds(
        Livewire::test('tickets.admin-ticket-list')->call('sort', 'category')
    );

    expect($ids)->toBe([$ticket->id]);
});

test('an unknown sort column falls back to the default', function () {
    Ticket::factory()->count(2)->create(['status' => 'open']);

    $component = Livewire::test('tickets.admin-ticket-list')
        ->set('sortBy', 'password')
        ->call('sort', 'password');

    expect($component->get('sortBy'))->toBe('password');

    $component->assertOk();
    expect($component->viewData('sortBy'))->toBe('created_at');
});

test('the sort order survives a filter', function () {
    $client = User::factory()->create();
    Ticket::factory()->count(3)->create(['user_id' => $client->id, 'status' => 'open']);

    $ids = listedTicketIds(
        Livewire::test('tickets.admin-ticket-list')
            ->call('sort', 'id')
            ->set('clientFilter', (string) $client->id)
    );

    expect($ids)->toBe(collect($ids)->sort()->values()->all());
});

test('changing a filter returns to the first page', function () {
    Ticket::factory()->count(25)->create(['status' => 'open']);

    $component = Livewire::test('tickets.admin-ticket-list')->call('gotoPage', 2);
    expect($component->viewData('tickets')->currentPage())->toBe(2);

    $component->set('search', 'a');

    expect($component->viewData('tickets')->currentPage())->toBe(1);
});

test('changing the sort column returns to the first page', function () {
    Ticket::factory()->count(25)->create(['status' => 'open']);

    $component = Livewire::test('tickets.admin-ticket-list')->call('gotoPage', 2);
    expect($component->viewData('tickets')->currentPage())->toBe(2);

    $component->call('sort', 'id');

    expect($component->viewData('tickets')->currentPage())->toBe(1);
});

test('a client is denied the admin listing even with filters in the url', function () {
    $client = User::factory()->create();

    $this->actingAs($client)
        ->get(route('admin.tickets', ['search' => 'impresora', 'clientFilter' => $client->id]))
        ->assertForbidden();
});
