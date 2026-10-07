<?php

use App\Enums\Level;
use App\Models\Area;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

test('the ticket list shows the header, the area tabs and the filters', function () {
    $area = Area::factory()->create(['title' => 'Comercial']);
    $client = User::factory()->withAreas($area)->create();
    Ticket::factory()->for($client)->count(2)->create(['area_id' => $area->id, 'status' => 'open']);

    $this->actingAs($client)
        ->get(route('ticket.index'))
        ->assertOk()
        ->assertSeeInOrder(['Mis tickets', 'Nuevo ticket', 'Comercial', 'cupos disponibles', 'Buscar', 'Estado'])
        ->assertSeeHtml('<span class="text-xs font-normal text-neutral-400">2</span>');
});

test('admins see how many ongoing tickets are assigned to them', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->count(2)->create(['status' => 'in_progress', 'assigned_to' => $admin->id]);
    Ticket::factory()->create(['status' => 'resolved', 'assigned_to' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-list')
        ->assertViewHas('tabs', fn ($tabs) => $tabs['assigned']['count'] === 2)
        ->assertSee('Asignados a mí');
});

test('each ticket row shows its id, priority level and client', function () {
    $client = User::factory()->create(['name' => 'Cliente Ficticio']);
    Ticket::factory()->for($client)->create(['status' => 'open', 'importance' => Level::High, 'urgency' => Level::High, 'title' => 'Sin conexión']);

    $this->actingAs($client)
        ->get(route('ticket.index'))
        ->assertSee('#TK-')
        ->assertSee('Sin conexión')
        ->assertSee('Crítica')
        ->assertSee('Cliente Ficticio');
});

test('the priority label follows the importance and urgency matrix', function (Level $importance, Level $urgency, string $label) {
    $ticket = Ticket::factory()->create(['importance' => $importance, 'urgency' => $urgency]);

    expect($ticket->priorityLabel())->toBe($label);
})->with([
    [Level::High, Level::High, 'Crítica'],
    [Level::Medium, Level::High, 'Alta'],
    [Level::High, Level::Low, 'Media'],
    [Level::Low, Level::Medium, 'Baja'],
]);

test('the admin ticket list shows the status filter instead of status tabs', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')
        ->assertSeeHtml('data-test="status-filter-trigger"')
        ->assertSee('En curso')
        ->assertDontSee('Resueltos');
});

test('the ticket detail header shows the ticket id, status and priority level', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create(['status' => 'open', 'importance' => Level::Low, 'urgency' => Level::Low]);

    $this->actingAs($client)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSeeInOrder(["#TK-{$ticket->id}", 'Abierto', 'Baja', 'Descripción', 'Propiedades']);
});

test('the dashboard uses the shared page header and metric cards', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Dashboard', 'Resumen de tus tickets', 'Mis tickets abiertos', 'Mis tickets recientes']);
});
