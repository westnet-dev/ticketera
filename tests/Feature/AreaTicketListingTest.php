<?php

use App\Models\Area;
use App\Models\Ticket;
use App\Models\TicketSetting;
use App\Models\User;
use Livewire\Livewire;

/**
 * "Mis tickets" lists one tab per area the requester belongs to, holding every
 * submitted ticket of that area, with a status filter, a search box and how
 * much of the area's ticket cap is left.
 */
function listingIds($tickets): array
{
    return $tickets->pluck('id')->all();
}

test('a client sees every submitted ticket of their area, not only their own', function () {
    $area = Area::factory()->create(['title' => 'Comercial']);
    $client = User::factory()->withAreas($area)->create();
    $own = Ticket::factory()->for($client)->create(['area_id' => $area->id, 'status' => 'open']);
    $teammates = Ticket::factory()->create(['area_id' => $area->id, 'status' => 'in_progress']);
    $otherArea = Ticket::factory()->create(['area_id' => Area::factory()->create()->id, 'status' => 'open']);

    $this->actingAs($client)
        ->get(route('ticket.index'))
        ->assertOk()
        ->assertSee('Comercial')
        ->assertSee($own->title)
        ->assertSee($teammates->title)
        ->assertDontSee($otherArea->title);
});

test('a client with several areas gets one tab per area and starts on the first one', function () {
    $comercial = Area::factory()->create(['title' => 'Comercial']);
    $tecnica = Area::factory()->create(['title' => 'Técnica']);
    $client = User::factory()->withAreas($tecnica, $comercial)->create();
    $comercialTicket = Ticket::factory()->create(['area_id' => $comercial->id, 'status' => 'open']);
    $tecnicaTicket = Ticket::factory()->create(['area_id' => $tecnica->id, 'status' => 'open']);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->assertSeeInOrder(['Comercial', 'Técnica'])
        ->assertSet('area', (string) $comercial->id)
        ->assertViewHas('tickets', fn ($tickets) => listingIds($tickets) === [$comercialTicket->id])
        ->call('selectTab', (string) $tecnica->id)
        ->assertSet('area', (string) $tecnica->id)
        ->assertViewHas('tickets', fn ($tickets) => listingIds($tickets) === [$tecnicaTicket->id]);
});

test('each area tab carries its count of ongoing tickets', function () {
    $comercial = Area::factory()->create(['title' => 'Comercial']);
    $tecnica = Area::factory()->create(['title' => 'Técnica']);
    $client = User::factory()->withAreas($comercial, $tecnica)->create();
    Ticket::factory()->count(2)->create(['area_id' => $comercial->id, 'status' => 'open']);
    Ticket::factory()->create(['area_id' => $comercial->id, 'status' => 'resolved']);
    Ticket::factory()->create(['area_id' => $tecnica->id, 'status' => 'paused']);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->assertViewHas('tabs', fn ($tabs) => $tabs[$comercial->id]['count'] === 2
            && $tabs[$tecnica->id]['count'] === 1);
});

test('an area the user does not belong to falls back to their first tab', function () {
    $comercial = Area::factory()->create(['title' => 'Comercial']);
    $foreign = Area::factory()->create(['title' => 'Ajena']);
    $client = User::factory()->withAreas($comercial)->create();
    $foreignTicket = Ticket::factory()->create(['area_id' => $foreign->id, 'status' => 'open']);

    $this->actingAs($client);

    Livewire::withQueryParams(['area' => (string) $foreign->id])
        ->test('tickets.ticket-list')
        ->assertSet('area', (string) $comercial->id)
        ->assertViewHas('tickets', fn ($tickets) => ! in_array($foreignTicket->id, listingIds($tickets), true));

    Livewire::test('tickets.ticket-list')
        ->call('selectTab', (string) $foreign->id)
        ->assertSet('area', (string) $comercial->id);
});

test('a client without areas gets a single tab with their own tickets', function () {
    $client = User::factory()->create();
    $own = Ticket::factory()->for($client)->create(['status' => 'open']);
    $someoneElses = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->assertViewHas('tabs', fn ($tabs) => $tabs->keys()->all() === ['none'] && $tabs['none']['label'] === 'Mis tickets')
        ->assertViewHas('tickets', fn ($tickets) => listingIds($tickets) === [$own->id])
        ->assertDontSee($someoneElses->title);
});

test('own tickets from an area the user left show up under "Sin área"', function () {
    $comercial = Area::factory()->create(['title' => 'Comercial']);
    $tecnica = Area::factory()->create(['title' => 'Técnica']);
    $client = User::factory()->withAreas($tecnica)->create();
    $leftBehind = Ticket::factory()->for($client)->create(['area_id' => $comercial->id, 'status' => 'open']);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->assertViewHas('tabs', fn ($tabs) => $tabs->keys()->map(fn ($key) => (string) $key)->all() === [(string) $tecnica->id, 'none']
            && $tabs['none']['label'] === 'Sin área')
        ->call('selectTab', 'none')
        ->assertViewHas('tickets', fn ($tickets) => listingIds($tickets) === [$leftBehind->id]);
});

test('a client with areas and nothing outside them gets no "Sin área" tab', function () {
    $area = Area::factory()->create();
    $client = User::factory()->withAreas($area)->create();
    Ticket::factory()->for($client)->create(['area_id' => $area->id, 'status' => 'open']);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->assertViewHas('tabs', fn ($tabs) => ! $tabs->has('none'));
});

test('the status filter narrows the active tab', function (string $status, array $expectedStatuses) {
    $area = Area::factory()->create();
    $client = User::factory()->withAreas($area)->create();

    foreach (['open', 'in_progress', 'paused', 'awaiting_response', 'resolved', 'cancelled'] as $ticketStatus) {
        Ticket::factory()->create(['area_id' => $area->id, 'status' => $ticketStatus]);
    }

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->set('status', $status)
        ->assertViewHas('tickets', fn ($tickets) => $tickets->pluck('status')->sort()->values()->all() === collect($expectedStatuses)->sort()->values()->all());
})->with([
    'en curso' => ['ongoing', ['open', 'in_progress', 'paused', 'awaiting_response']],
    'finalizados' => ['finished', ['resolved', 'cancelled']],
    'todos' => ['all', ['open', 'in_progress', 'paused', 'awaiting_response', 'resolved', 'cancelled']],
    'valor inválido' => ['bogus', ['open', 'in_progress', 'paused', 'awaiting_response']],
]);

test('"Por validar" lists the area\'s tickets awaiting validation from any author', function () {
    $area = Area::factory()->create();
    $client = User::factory()->withAreas($area)->create();
    $own = Ticket::factory()->for($client)->awaitingValidation()->create(['area_id' => $area->id]);
    $teammates = Ticket::factory()->awaitingValidation()->create(['area_id' => $area->id]);
    Ticket::factory()->validated()->create(['area_id' => $area->id]);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->set('status', 'pending_validation')
        ->assertViewHas('tickets', fn ($tickets) => collect(listingIds($tickets))->sort()->values()->all() === collect([$own->id, $teammates->id])->sort()->values()->all())
        ->assertSee(__('Estos tickets esperan tu confirmación'));
});

test('"Borradores" only lists the user\'s own drafts', function () {
    $area = Area::factory()->create();
    $client = User::factory()->withAreas($area)->create();
    $own = Ticket::factory()->for($client)->draft()->create(['area_id' => $area->id]);
    $teammates = Ticket::factory()->draft()->create(['area_id' => $area->id]);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->set('status', 'draft')
        ->assertViewHas('tickets', fn ($tickets) => listingIds($tickets) === [$own->id]);

    Livewire::test('tickets.ticket-list')
        ->set('status', 'all')
        ->assertViewHas('tickets', fn ($tickets) => ! in_array($teammates->id, listingIds($tickets), true));
});

test('the search matches titles and ticket numbers within the active tab and status', function () {
    $area = Area::factory()->create();
    $client = User::factory()->withAreas($area)->create();
    $printer = Ticket::factory()->create(['area_id' => $area->id, 'status' => 'open', 'title' => 'Falla la impresora del piso 2']);
    $resolvedPrinter = Ticket::factory()->create(['area_id' => $area->id, 'status' => 'resolved', 'title' => 'Impresora sin tóner']);
    $other = Ticket::factory()->create(['area_id' => $area->id, 'status' => 'open', 'title' => 'Alta de usuario de correo']);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->set('search', 'impresora')
        ->assertViewHas('tickets', fn ($tickets) => listingIds($tickets) === [$printer->id])
        ->set('search', "#TK-{$other->id}")
        ->assertViewHas('tickets', fn ($tickets) => listingIds($tickets) === [$other->id])
        ->set('status', 'all')
        ->set('search', 'impresora')
        ->assertViewHas('tickets', fn ($tickets) => count(listingIds($tickets)) === 2 && in_array($resolvedPrinter->id, listingIds($tickets), true));
});

test('searching a ticket number from another area finds nothing', function () {
    $area = Area::factory()->create();
    $client = User::factory()->withAreas($area)->create();
    $foreign = Ticket::factory()->create(['area_id' => Area::factory()->create()->id, 'status' => 'open']);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->set('search', "#TK-{$foreign->id}")
        ->assertViewHas('tickets', fn ($tickets) => $tickets->isEmpty())
        ->assertSee(__('Ningún ticket coincide con los filtros aplicados.'));
});

test('the active area shows how many ticket slots it has left', function () {
    TicketSetting::current()->update(['max_open_tickets_per_area' => 5]);
    $area = Area::factory()->create();
    $client = User::factory()->withAreas($area)->create();
    Ticket::factory()->count(3)->create(['area_id' => $area->id, 'status' => 'open']);
    Ticket::factory()->count(2)->draft()->create(['area_id' => $area->id]);
    Ticket::factory()->create(['area_id' => $area->id, 'status' => 'resolved']);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->assertViewHas('quota', ['used' => 3, 'max' => 5, 'remaining' => 2])
        ->assertSee('2 cupos disponibles')
        ->assertDontSee(__('Esta área alcanzó el máximo de tickets sin cerrar. No se pueden crear tickets nuevos para ella hasta que se resuelva o cancele alguno.'));
});

test('a full area shows no slots left and a warning', function () {
    TicketSetting::current()->update(['max_open_tickets_per_area' => 2]);
    $area = Area::factory()->create();
    $client = User::factory()->withAreas($area)->create();
    Ticket::factory()->count(3)->create(['area_id' => $area->id, 'status' => 'open']);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->assertViewHas('quota', fn (array $quota) => $quota['remaining'] === 0)
        ->assertSee('0 cupos disponibles')
        ->assertSee(__('Esta área alcanzó el máximo de tickets sin cerrar. No se pueden crear tickets nuevos para ella hasta que se resuelva o cancele alguno.'));

    expect($client->can('create', [Ticket::class, $area]))->toBeFalse();
});

test('the remaining slots agree with the creation rule', function (int $openTickets) {
    TicketSetting::current()->update(['max_open_tickets_per_area' => 3]);
    $area = Area::factory()->create();
    $client = User::factory()->withAreas($area)->create();
    Ticket::factory()->count($openTickets)->create(['area_id' => $area->id, 'status' => 'open']);

    expect($client->can('create', [Ticket::class, $area]))->toBe($client->remainingTicketSlots($area) > 0);
})->with([0, 2, 3, 4]);

test('a client without areas sees their own ticket slots', function () {
    TicketSetting::current()->update(['max_open_tickets_per_area' => 5]);
    $client = User::factory()->create();
    Ticket::factory()->for($client)->count(4)->create(['status' => 'open']);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->assertViewHas('quota', ['used' => 4, 'max' => 5, 'remaining' => 1])
        ->assertSee('1 cupo disponible');
});

test('the "Sin área" tab of a user with areas shows no quota', function () {
    $tecnica = Area::factory()->create();
    $client = User::factory()->withAreas($tecnica)->create();
    Ticket::factory()->for($client)->create(['area_id' => null, 'status' => 'open']);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->call('selectTab', 'none')
        ->assertViewHas('quota', null);
});

test('admins see no quota and get a tab with the tickets assigned to them', function () {
    $area = Area::factory()->create();
    $admin = User::factory()->admin()->withAreas($area)->create();
    $assigned = Ticket::factory()->create(['status' => 'in_progress', 'assigned_to' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-list')
        ->assertViewHas('quota', null)
        ->assertViewHas('tabs', fn ($tabs) => $tabs['assigned']['label'] === 'Asignados a mí' && $tabs['assigned']['count'] === 1)
        ->call('selectTab', 'assigned')
        ->assertViewHas('tickets', fn ($tickets) => listingIds($tickets) === [$assigned->id]);
});

test('clients get no assigned tab', function () {
    $client = User::factory()->create();
    Ticket::factory()->create(['status' => 'open', 'assigned_to' => $client->id]);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-list')
        ->assertViewHas('tabs', fn ($tabs) => ! $tabs->has('assigned'));
});

test('the old status pages redirect to the listing with the matching filter', function (string $route, string $status) {
    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertRedirect(route('ticket.index', ['status' => $status]));
})->with([
    ['ticket.finished', 'finished'],
    ['ticket.drafts', 'draft'],
    ['ticket.pending-validation', 'pending_validation'],
]);

test('the status in the URL preselects the filter', function () {
    $client = User::factory()->create();
    $resolved = Ticket::factory()->for($client)->create(['status' => 'resolved']);
    $open = Ticket::factory()->for($client)->create(['status' => 'open']);

    $this->actingAs($client)
        ->get(route('ticket.index', ['status' => 'finished']))
        ->assertOk()
        ->assertSee($resolved->title)
        ->assertDontSee($open->title);
});

test('the client dashboard counts the area tickets the user can validate', function () {
    $area = Area::factory()->create();
    $client = User::factory()->withAreas($area)->create();
    Ticket::factory()->for($client)->awaitingValidation()->create(['area_id' => $area->id]);
    Ticket::factory()->awaitingValidation()->create(['area_id' => $area->id]);
    Ticket::factory()->awaitingValidation()->create(['area_id' => Area::factory()->create()->id]);

    $this->actingAs($client);

    Livewire::test('dashboard')
        ->assertViewHas('pendingValidationCount', 2);
});
