<?php

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Livewire\Livewire;

test('a client can view their own ticket', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSee($ticket->title);
});

test('a client cannot view another client\'s ticket', function () {
    $owner = User::factory()->create();
    $ticket = Ticket::factory()->for($owner)->create();

    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->get(route('ticket.show', $ticket))
        ->assertForbidden();
});

test('an admin can view any ticket', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($admin)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSee($ticket->title);
});

test('a client can send a message on their ticket', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket])
        ->set('body', 'Necesito ayuda con mi conexión.')
        ->call('send')
        ->assertSet('body', '');

    expect(TicketMessage::where('ticket_id', $ticket->id)->count())->toBe(1);

    $message = TicketMessage::first();
    expect($message->user_id)->toBe($user->id)
        ->and($message->body)->toBe('<p>Necesito ayuda con mi conexión.</p>');
});

test('a message requires a body', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket])
        ->set('body', '')
        ->call('send')
        ->assertHasErrors(['body' => 'required']);
});
