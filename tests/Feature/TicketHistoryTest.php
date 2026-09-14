<?php

use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Livewire\Livewire;

test('changing a ticket status records a history entry', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'in_progress');

    $entry = TicketHistory::where('ticket_id', $ticket->id)->where('field', 'status')->sole();

    expect($entry->from_value)->toBe('open');
    expect($entry->to_value)->toBe('in_progress');
    expect($entry->user_id)->toBe($admin->id);
});

test('approving a ticket in triage records a history entry', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['triage_status' => TriageStatus::Pending]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-triage-actions', ['ticket' => $ticket])
        ->call('approve');

    $entry = TicketHistory::where('ticket_id', $ticket->id)->where('field', 'triage_status')->sole();

    expect($entry->from_value)->toBe('pending');
    expect($entry->to_value)->toBe('approved');
    expect($entry->user_id)->toBe($admin->id);
});

test('rejecting a ticket in triage records a history entry', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['triage_status' => TriageStatus::Pending]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-triage-actions', ['ticket' => $ticket])
        ->set('rejectionReason', 'Falta más información sobre el problema.')
        ->call('reject');

    $entry = TicketHistory::where('ticket_id', $ticket->id)->where('field', 'triage_status')->sole();

    expect($entry->from_value)->toBe('pending');
    expect($entry->to_value)->toBe('rejected');
});

test('assigning, reassigning, and unassigning a ticket record history entries', function () {
    $admin = User::factory()->admin()->create();
    $firstAgent = User::factory()->admin()->create();
    $secondAgent = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')->call('assign', $ticket->id, (string) $firstAgent->id);
    Livewire::test('tickets.admin-ticket-list')->call('assign', $ticket->id, (string) $secondAgent->id);
    Livewire::test('tickets.admin-ticket-list')->call('assign', $ticket->id, '');

    $entries = TicketHistory::where('ticket_id', $ticket->id)->where('field', 'assigned_to')->orderBy('id')->get();

    expect($entries)->toHaveCount(3);
    expect($entries[0]->from_value)->toBeNull();
    expect($entries[0]->to_value)->toBe((string) $firstAgent->id);
    expect($entries[1]->from_value)->toBe((string) $firstAgent->id);
    expect($entries[1]->to_value)->toBe((string) $secondAgent->id);
    expect($entries[2]->from_value)->toBe((string) $secondAgent->id);
    expect($entries[2]->to_value)->toBeNull();
});

test('submitting a draft ticket records a status change', function () {
    $owner = User::factory()->create();
    $draft = Ticket::factory()->draft()->create(['user_id' => $owner->id]);
    $draft->images()->create(['image_path' => 'images/tickets/existing.jpg']);

    $this->actingAs($owner);

    Livewire::test('tickets.create-ticket', ['draft' => $draft])
        ->set('title', 'Falla en el servicio de internet')
        ->set('description', 'No hay conexión desde esta mañana en toda la oficina.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->call('submit');

    $entry = TicketHistory::where('ticket_id', $draft->id)->where('field', 'status')->sole();

    expect($entry->from_value)->toBe('draft');
    expect($entry->to_value)->toBe('open');
});

test('resubmitting a rejected ticket records a triage status change', function () {
    $owner = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $owner->id, 'triage_status' => TriageStatus::Rejected]);
    $ticket->images()->create(['image_path' => 'images/tickets/existing.jpg']);

    $this->actingAs($owner);

    Livewire::test('tickets.revise-ticket', ['ticket' => $ticket])
        ->set('title', 'Título corregido y más descriptivo')
        ->set('description', 'Descripción corregida con más detalle sobre el problema.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->call('save');

    $entry = TicketHistory::where('ticket_id', $ticket->id)->where('field', 'triage_status')->sole();

    expect($entry->from_value)->toBe('rejected');
    expect($entry->to_value)->toBe('pending');
});

test('updating unwatched ticket fields does not record history', function () {
    $owner = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $owner->id, 'triage_status' => TriageStatus::Rejected]);
    $ticket->images()->create(['image_path' => 'images/tickets/existing.jpg']);

    $this->actingAs($owner);

    Livewire::test('tickets.revise-ticket', ['ticket' => $ticket])
        ->set('title', 'Título corregido y más descriptivo')
        ->set('description', 'Descripción corregida con más detalle sobre el problema.')
        ->set('priority', 9)
        ->set('urgency', 8)
        ->set('impact', 7)
        ->call('save');

    // The revise flow always moves triage_status rejected -> pending, so that one entry is expected;
    // title/description/priority/urgency/impact must not produce any history entries of their own.
    expect(TicketHistory::where('ticket_id', $ticket->id)->count())->toBe(1);
    expect(TicketHistory::where('ticket_id', $ticket->id)->where('field', 'triage_status')->exists())->toBeTrue();
});

test('the ticket detail page renders the history timeline in chronological order', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])->call('updateStatus', 'in_progress');
    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])->call('updateStatus', 'paused');

    $this->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSeeInOrder([__('En Progreso'), __('Pausado')]);
});

test('the history timeline shows a fallback label for a since-deleted assigned user', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')->call('assign', $ticket->id, (string) $agent->id);

    $agent->forceDelete();

    $this->get(route('ticket.show', $ticket->refresh()))
        ->assertOk()
        ->assertSee(__('Usuario eliminado'));
});

test('the history timeline shows an empty state when a ticket has no recorded changes', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($admin)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSee(__('Todavía no hay cambios registrados para este ticket.'));
});
