<?php

use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

test('the ticket list shows summary cards and tab counts for the user', function () {
    $client = User::factory()->create();
    Ticket::factory()->for($client)->count(2)->create(['status' => 'open']);
    Ticket::factory()->for($client)->create(['status' => 'resolved']);
    Ticket::factory()->for($client)->draft()->create();
    Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($client)
        ->get(route('ticket.index'))
        ->assertOk()
        ->assertViewHas('ticketCounts', fn (array $counts) => $counts['total'] === 3
            && $counts['ongoing'] === 2
            && $counts['finished'] === 1
            && $counts['drafts'] === 1)
        ->assertSeeInOrder(['Mis tickets', 'Total de tickets', 'En curso', 'Por validar', 'Finalizados'])
        ->assertSee('2 en curso');
});

test('admins see how many ongoing tickets are assigned to them', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->count(2)->create(['status' => 'in_progress', 'assigned_to' => $admin->id]);
    Ticket::factory()->create(['status' => 'resolved', 'assigned_to' => $admin->id]);

    $this->actingAs($admin)
        ->get(route('ticket.index'))
        ->assertOk()
        ->assertSee('2 asignados a vos');
});

test('each ticket row shows its id, priority level and client', function () {
    $client = User::factory()->create(['name' => 'Cliente Ficticio']);
    Ticket::factory()->for($client)->create(['status' => 'open', 'priority' => 8, 'title' => 'Sin conexión']);

    $this->actingAs($client)
        ->get(route('ticket.index'))
        ->assertSee('#TK-')
        ->assertSee('Sin conexión')
        ->assertSee('Alta')
        ->assertSee('Cliente Ficticio');
});

test('priority scores are bucketed into levels', function (int $priority, string $label) {
    expect(Ticket::factory()->make(['priority' => $priority])->priorityLabel())->toBe($label);
})->with([
    [10, 'Alta'],
    [7, 'Alta'],
    [6, 'Media'],
    [4, 'Media'],
    [3, 'Baja'],
    [1, 'Baja'],
]);

test('the admin ticket list tabs carry a count for each filter', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->count(2)->create(['status' => 'open', 'assigned_to' => null]);
    Ticket::factory()->create(['status' => 'in_progress', 'assigned_to' => $admin->id]);
    Ticket::factory()->create(['status' => 'resolved']);
    Ticket::factory()->create(['status' => 'cancelled', 'assigned_to' => $admin->id]);
    Ticket::factory()->create(['status' => 'open', 'triage_status' => TriageStatus::Pending]);

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')
        ->assertViewHas('tabCounts', [
            'all' => 4,
            'unassigned' => 2,
            'resolved' => 1,
            'cancelled' => 1,
        ]);
});

test('the ticket detail header shows the ticket id, status and priority level', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create(['status' => 'open', 'priority' => 2]);

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
