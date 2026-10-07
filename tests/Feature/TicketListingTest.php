<?php

use App\Models\Ticket;
use App\Models\User;

test('an in-progress ticket appears only in the "en curso" listing', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create(['status' => 'in_progress']);

    $this->actingAs($client)->get(route('ticket.index'))->assertSee($ticket->title);
    $this->actingAs($client)->get(route('ticket.index', ['status' => 'finished']))->assertDontSee($ticket->title);
    $this->actingAs($client)->get(route('ticket.index', ['status' => 'draft']))->assertDontSee($ticket->title);
});

test('a paused ticket appears only in the "en curso" listing', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create(['status' => 'paused']);

    $this->actingAs($client)->get(route('ticket.index'))->assertSee($ticket->title);
    $this->actingAs($client)->get(route('ticket.index', ['status' => 'finished']))->assertDontSee($ticket->title);
});

test('a ticket awaiting response appears only in the "en curso" listing', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->awaitingResponse()->create();

    $this->actingAs($client)->get(route('ticket.index'))->assertSee($ticket->title);
    $this->actingAs($client)->get(route('ticket.index', ['status' => 'finished']))->assertDontSee($ticket->title);
    $this->actingAs($client)->get(route('ticket.index', ['status' => 'draft']))->assertDontSee($ticket->title);
});

test('a resolved ticket appears only in the "finalizados" listing', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create(['status' => 'resolved']);

    $this->actingAs($client)->get(route('ticket.index', ['status' => 'finished']))->assertSee($ticket->title);
    $this->actingAs($client)->get(route('ticket.index'))->assertDontSee($ticket->title);
});

test('a cancelled ticket appears only in the "finalizados" listing', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create(['status' => 'cancelled']);

    $this->actingAs($client)->get(route('ticket.index', ['status' => 'finished']))->assertSee($ticket->title);
    $this->actingAs($client)->get(route('ticket.index'))->assertDontSee($ticket->title);
});

test('a draft ticket appears only in the "borradores" listing', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->draft()->create(['title' => 'Ticket sin terminar']);

    $this->actingAs($client)->get(route('ticket.index', ['status' => 'draft']))->assertSee($ticket->title);
    $this->actingAs($client)->get(route('ticket.index'))->assertDontSee($ticket->title);
    $this->actingAs($client)->get(route('ticket.index', ['status' => 'finished']))->assertDontSee($ticket->title);
});

test('empty listings show a status-specific message', function () {
    $client = User::factory()->create();

    $this->actingAs($client)
        ->get(route('ticket.index', ['status' => 'finished']))
        ->assertSee('No hay tickets finalizados.');

    $this->actingAs($client)
        ->get(route('ticket.index', ['status' => 'draft']))
        ->assertSee('No tienes borradores.');
});
