<?php

use App\Enums\Difficulty;
use App\Enums\Level;
use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Livewire\Livewire;

test('a new ticket starts without a difficulty', function () {
    expect(Ticket::factory()->create()->difficulty)->toBeNull();
});

test('an admin can estimate a ticket\'s difficulty', function (Difficulty $difficulty) {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-difficulty-selector', ['ticket' => $ticket])
        ->call('setDifficulty', (string) $difficulty->value)
        ->assertOk();

    expect($ticket->refresh()->difficulty)->toBe($difficulty);
})->with(Difficulty::cases());

test('an admin can change and clear the estimate', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->withDifficulty(Difficulty::Five)->create();

    $this->actingAs($admin);

    $component = Livewire::test('tickets.ticket-difficulty-selector', ['ticket' => $ticket])
        ->call('setDifficulty', '13');

    expect($ticket->refresh()->difficulty)->toBe(Difficulty::Thirteen);

    $component->call('setDifficulty', '');

    expect($ticket->refresh()->difficulty)->toBeNull();
});

test('an admin can estimate a ticket that is still in triage', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['triage_status' => TriageStatus::Pending]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-difficulty-selector', ['ticket' => $ticket])
        ->call('setDifficulty', '3');

    expect($ticket->refresh()->difficulty)->toBe(Difficulty::Three);
});

test('values off the fibonacci scale are ignored', function (string $value) {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->withDifficulty(Difficulty::Two)->create();

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-difficulty-selector', ['ticket' => $ticket])
        ->call('setDifficulty', $value);

    expect($ticket->refresh()->difficulty)->toBe(Difficulty::Two);
})->with(['4', '0', '21', '5abc', 'texto']);

test('a client cannot estimate their own ticket', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create();

    $this->actingAs($client);

    Livewire::test('tickets.ticket-difficulty-selector', ['ticket' => $ticket])
        ->call('setDifficulty', '8')
        ->assertForbidden();

    expect($ticket->refresh()->difficulty)->toBeNull();
});

test('a guest cannot estimate a ticket', function () {
    $ticket = Ticket::factory()->create();

    Livewire::test('tickets.ticket-difficulty-selector', ['ticket' => $ticket])
        ->call('setDifficulty', '8')
        ->assertForbidden();

    expect($ticket->refresh()->difficulty)->toBeNull();
});

test('an admin sees the difficulty selector on the ticket detail', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->withDifficulty(Difficulty::Thirteen)->create();

    $this->actingAs($admin)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSee('Dificultad')
        ->assertSeeLivewire('tickets.ticket-difficulty-selector');
});

test('a client never sees the difficulty on their ticket', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->withDifficulty(Difficulty::Thirteen)->create();

    $this->actingAs($client)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertDontSee('Dificultad')
        ->assertDontSeeLivewire('tickets.ticket-difficulty-selector');
});

test('the author editing their ticket leaves the difficulty untouched', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->withDifficulty(Difficulty::Eight)->create([
        'triage_status' => TriageStatus::Pending,
        'status' => 'open',
    ]);

    $this->actingAs($client);

    Livewire::test('tickets.edit-ticket', ['ticket' => $ticket])
        ->set('title', 'Título editado y más descriptivo')
        ->set('description', 'Descripción editada con más detalle sobre el problema.')
        ->set('importance', Level::High->value)
        ->set('urgency', Level::High->value)
        ->set('impact', Level::Medium->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($ticket->refresh()->difficulty)->toBe(Difficulty::Eight);
});

test('estimating a ticket records a history entry by the admin', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-difficulty-selector', ['ticket' => $ticket])
        ->call('setDifficulty', '3');

    $entry = TicketHistory::where('ticket_id', $ticket->id)->where('field', 'difficulty')->sole();

    expect($entry->from_value)->toBeNull()
        ->and($entry->to_value)->toBe('3')
        ->and($entry->user_id)->toBe($admin->id);
});

test('only admins see difficulty entries in the history', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create(['status' => 'open']);

    $this->actingAs($admin);
    $ticket->update(['difficulty' => Difficulty::Three, 'status' => 'in_progress']);

    Livewire::test('tickets.ticket-history-timeline', ['ticket' => $ticket])
        ->assertSee('Dificultad')
        ->assertSee('Sin estimar')
        ->assertSee('En Progreso');

    $this->actingAs($client);

    Livewire::test('tickets.ticket-history-timeline', ['ticket' => $ticket])
        ->assertDontSee('Dificultad')
        ->assertDontSee('Sin estimar')
        ->assertSee('En Progreso');
});
