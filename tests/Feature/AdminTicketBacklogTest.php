<?php

use App\Models\Ticket;
use App\Models\User;

test('an admin sees in their ticket list a ticket created by someone else but assigned to them', function () {
    $admin = User::factory()->admin()->create();
    $creator = User::factory()->create();
    Ticket::factory()->create([
        'user_id' => $creator->id,
        'assigned_to' => $admin->id,
        'title' => 'Pedido de otro asignado a mí',
        'status' => 'open',
    ]);

    $this->actingAs($admin)
        ->get(route('ticket.index'))
        ->assertOk()
        ->assertSee('Pedido de otro asignado a mí');
});

test('an admin still sees tickets they created themselves', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->create([
        'user_id' => $admin->id,
        'assigned_to' => null,
        'title' => 'Mi propio pedido',
        'status' => 'open',
    ]);

    $this->actingAs($admin)
        ->get(route('ticket.index'))
        ->assertOk()
        ->assertSee('Mi propio pedido');
});

test('a resolved ticket assigned to an admin appears only in the finished tab', function () {
    $admin = User::factory()->admin()->create();
    $creator = User::factory()->create();
    Ticket::factory()->create([
        'user_id' => $creator->id,
        'assigned_to' => $admin->id,
        'status' => 'resolved',
        'title' => 'Pedido resuelto asignado',
    ]);

    $this->actingAs($admin);

    $this->get(route('ticket.index'))->assertOk()->assertDontSee('Pedido resuelto asignado');
    $this->get(route('ticket.finished'))->assertOk()->assertSee('Pedido resuelto asignado');
});

test('a client does not see tickets assigned to them, only tickets they created', function () {
    $client = User::factory()->create();
    $other = User::factory()->create();
    Ticket::factory()->create([
        'user_id' => $other->id,
        'assigned_to' => $client->id,
        'title' => 'Ticket ajeno',
        'status' => 'open',
    ]);

    $this->actingAs($client)
        ->get(route('ticket.index'))
        ->assertOk()
        ->assertDontSee('Ticket ajeno');
});

test('the admin tickets list still shows tickets not assigned to the logged-in admin', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    Ticket::factory()->create([
        'assigned_to' => $otherAdmin->id,
        'title' => 'Asignado a otro admin',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.tickets'))
        ->assertOk()
        ->assertSee('Asignado a otro admin');
});
