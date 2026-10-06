<?php

use App\Models\Ticket;
use App\Models\TicketHistory;
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

test('the author replying to a ticket awaiting response puts it back in progress', function () {
    $author = User::factory()->create();
    $ticket = Ticket::factory()->for($author)->awaitingResponse()->create();

    $this->actingAs($author);

    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket])
        ->set('body', 'Te paso la captura que me pediste.')
        ->call('send')
        ->assertHasNoErrors();

    expect($ticket->refresh()->status)->toBe('in_progress')
        ->and(TicketMessage::where('ticket_id', $ticket->id)->count())->toBe(1);

    $entry = TicketHistory::where('ticket_id', $ticket->id)->where('field', 'status')->sole();

    expect($entry->from_value)->toBe('awaiting_response')
        ->and($entry->to_value)->toBe('in_progress')
        ->and($entry->user_id)->toBe($author->id);
});

test('an admin message does not resume a ticket awaiting response', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->awaitingResponse()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket])
        ->set('body', '¿Pudiste revisar lo que te pedimos?')
        ->call('send')
        ->assertHasNoErrors();

    expect($ticket->refresh()->status)->toBe('awaiting_response');
});

test('an author message on a paused ticket does not change its status', function () {
    $author = User::factory()->create();
    $ticket = Ticket::factory()->for($author)->create(['status' => 'paused']);

    $this->actingAs($author);

    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket])
        ->set('body', '¿Hay novedades?')
        ->call('send')
        ->assertHasNoErrors();

    expect($ticket->refresh()->status)->toBe('paused');
});

test('an invalid reply leaves a ticket awaiting response untouched', function () {
    $author = User::factory()->create();
    $ticket = Ticket::factory()->for($author)->awaitingResponse()->create();

    $this->actingAs($author);

    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket])
        ->set('body', '')
        ->call('send')
        ->assertHasErrors(['body' => 'required']);

    expect($ticket->refresh()->status)->toBe('awaiting_response')
        ->and(TicketMessage::where('ticket_id', $ticket->id)->exists())->toBeFalse();
});

test('only the author sees that the team is waiting for their reply', function () {
    $author = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->for($author)->awaitingResponse()->create();
    $hint = 'El equipo está esperando tu respuesta para continuar.';

    $this->actingAs($author);
    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket])->assertSee($hint);

    $this->actingAs($admin);
    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket])->assertDontSee($hint);

    $ticket->update(['status' => 'in_progress']);

    $this->actingAs($author);
    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket->refresh()])->assertDontSee($hint);
});
