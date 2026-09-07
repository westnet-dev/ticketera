<?php

use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

test('an admin sees app-wide ticket metrics on the dashboard', function () {
    $admin = User::factory()->admin()->create();
    $clientA = User::factory()->create();
    $clientB = User::factory()->create();

    Ticket::factory()->for($clientA)->create(['status' => 'open', 'priority' => 10, 'assigned_to' => null]);
    Ticket::factory()->for($clientB)->create(['status' => 'resolved', 'priority' => 2, 'assigned_to' => $admin->id]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('Tickets por estado'))
        ->assertSee(__('Sin asignar'))
        ->assertSee('1');
});

test('a client only sees their own tickets on the dashboard', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    Ticket::factory()->for($owner)->create(['title' => 'Mi ticket de conexión', 'status' => 'open']);
    Ticket::factory()->for($other)->create(['title' => 'Ticket de otro cliente']);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Mi ticket de conexión')
        ->assertDontSee('Ticket de otro cliente');
});

test('a client dashboard does not expose admin-only metrics', function () {
    $client = User::factory()->create();

    $this->actingAs($client)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(__('Usuarios'))
        ->assertDontSee(__('Sin asignar'));
});

test('the admin dashboard shows the average priority, urgency and impact across all tickets', function () {
    $admin = User::factory()->admin()->create();
    $clientA = User::factory()->create();
    $clientB = User::factory()->create();

    Ticket::factory()->for($clientA)->create(['priority' => 10, 'urgency' => 7, 'impact' => 3]);
    Ticket::factory()->for($clientB)->create(['priority' => 4, 'urgency' => 1, 'impact' => 9]);

    $this->actingAs($admin);

    Livewire::test('dashboard')
        ->assertViewHas('avgPriority', 7.0)
        ->assertViewHas('avgUrgency', 4.0)
        ->assertViewHas('avgImpact', 6.0);
});

test('the client dashboard averages only include the client\'s own tickets', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    Ticket::factory()->for($owner)->create(['priority' => 8, 'urgency' => 6, 'impact' => 2]);
    Ticket::factory()->for($other)->create(['priority' => 1, 'urgency' => 1, 'impact' => 1]);

    $this->actingAs($owner);

    Livewire::test('dashboard')
        ->assertViewHas('avgPriority', 8.0)
        ->assertViewHas('avgUrgency', 6.0)
        ->assertViewHas('avgImpact', 2.0);
});
