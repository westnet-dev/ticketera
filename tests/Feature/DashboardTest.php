<?php

use App\Enums\Level;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

test('an admin sees app-wide ticket metrics on the dashboard', function () {
    $admin = User::factory()->admin()->create();
    $clientA = User::factory()->create();
    $clientB = User::factory()->create();

    Ticket::factory()->for($clientA)->create(['status' => 'open', 'importance' => Level::High, 'assigned_to' => null]);
    Ticket::factory()->for($clientB)->create(['status' => 'resolved', 'importance' => Level::Low, 'assigned_to' => $admin->id]);

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

test('the admin dashboard shows how many tickets fall in each priority level', function () {
    $admin = User::factory()->admin()->create();
    $clientA = User::factory()->create();
    $clientB = User::factory()->create();

    Ticket::factory()->for($clientA)->count(2)->create(['importance' => Level::High, 'urgency' => Level::High]);
    Ticket::factory()->for($clientB)->create(['importance' => Level::Low, 'urgency' => Level::Low]);

    $this->actingAs($admin);

    Livewire::test('dashboard')
        ->assertViewHas('ticketsByPriority', fn ($counts) => $counts->all() === [1 => 1, 4 => 2])
        ->assertSee('Tickets por prioridad')
        ->assertSeeInOrder(['Crítica', '2', 'Alta', '0', 'Media', '0', 'Baja', '1']);
});

test('the client dashboard priority distribution only includes the client\'s own tickets', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    Ticket::factory()->for($owner)->create(['importance' => Level::Medium, 'urgency' => Level::Medium]);
    Ticket::factory()->for($other)->create(['importance' => Level::High, 'urgency' => Level::High]);

    $this->actingAs($owner);

    Livewire::test('dashboard')
        ->assertViewHas('ticketsByPriority', fn ($counts) => $counts->all() === [2 => 1]);
});
