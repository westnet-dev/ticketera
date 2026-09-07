<?php

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\User;

test('a client sees both resolved and cancelled tickets in the finished tickets view', function () {
    $user = User::factory()->create(['role' => Role::Client]);

    $resolvedTicket = Ticket::factory()->for($user)->create(['status' => 'resolved']);
    $cancelledTicket = Ticket::factory()->for($user)->create(['status' => 'cancelled']);
    $openTicket = Ticket::factory()->for($user)->create(['status' => 'open']);

    $this->actingAs($user)
        ->get(route('ticket.finished'))
        ->assertOk()
        ->assertSee($resolvedTicket->title)
        ->assertSee($cancelledTicket->title)
        ->assertDontSee($openTicket->title);
});

test('a client with no finished tickets sees an empty state', function () {
    $user = User::factory()->create(['role' => Role::Client]);
    Ticket::factory()->for($user)->create(['status' => 'open']);

    $this->actingAs($user)
        ->get(route('ticket.finished'))
        ->assertOk()
        ->assertSee(__('No tienes tickets finalizados.'));
});

test('a client cannot see another client\'s finished tickets', function () {
    $owner = User::factory()->create();
    $ownerResolvedTicket = Ticket::factory()->for($owner)->create(['status' => 'resolved']);

    $intruder = User::factory()->create(['role' => Role::Client]);

    $this->actingAs($intruder)
        ->get(route('ticket.finished'))
        ->assertOk()
        ->assertDontSee($ownerResolvedTicket->title);
});

test('the in-progress tickets view still excludes finished and paused tickets', function () {
    $user = User::factory()->create(['role' => Role::Client]);

    $openTicket = Ticket::factory()->for($user)->create(['status' => 'open']);
    $resolvedTicket = Ticket::factory()->for($user)->create(['status' => 'resolved']);

    $this->actingAs($user)
        ->get(route('ticket.index'))
        ->assertOk()
        ->assertSee($openTicket->title)
        ->assertDontSee($resolvedTicket->title);
});

test('a paused ticket still appears in the "en curso" view', function () {
    $user = User::factory()->create(['role' => Role::Client]);
    $pausedTicket = Ticket::factory()->for($user)->create(['status' => 'paused']);

    $this->actingAs($user)
        ->get(route('ticket.index'))
        ->assertOk()
        ->assertSee($pausedTicket->title);
});
