<?php

use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Livewire\Livewire;

/**
 * Admins can assign a ticket from its detail page, under the same rules as
 * the admin list: only on approved tickets and only to active admins.
 */
test('an admin assigns a ticket from its detail page and the history records it', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->admin()->create(['name' => 'Agente Ficticio']);
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($admin)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSee('Asignado a')
        ->assertSeeLivewire('tickets.ticket-assignee-selector');

    Livewire::test('tickets.ticket-assignee-selector', ['ticket' => $ticket])
        ->assertSee('Agente Ficticio')
        ->call('assign', (string) $agent->id);

    $entry = TicketHistory::where('ticket_id', $ticket->id)->where('field', 'assigned_to')->latest('id')->first();

    expect($ticket->refresh()->assigned_to)->toBe($agent->id)
        ->and($entry->to_value)->toBe((string) $agent->id)
        ->and($entry->user_id)->toBe($admin->id);
});

test('an admin unassigns a ticket from its detail page', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open', 'assigned_to' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-assignee-selector', ['ticket' => $ticket])
        ->call('assign', '');

    expect($ticket->refresh()->assigned_to)->toBeNull();
});

test('picking the current assignee changes nothing', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open', 'assigned_to' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-assignee-selector', ['ticket' => $ticket])
        ->call('assign', (string) $admin->id);

    expect(TicketHistory::where('ticket_id', $ticket->id)->where('field', 'assigned_to')->exists())->toBeFalse();
});

test('a client sees no assignee field and cannot assign', function () {
    $client = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->for($client)->create(['status' => 'open']);

    $this->actingAs($client)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertDontSee('Asignado a')
        ->assertDontSeeLivewire('tickets.ticket-assignee-selector');

    Livewire::test('tickets.ticket-assignee-selector', ['ticket' => $ticket])
        ->call('assign', (string) $admin->id)
        ->assertForbidden();

    expect($ticket->refresh()->assigned_to)->toBeNull();
});

test('a ticket pending triage shows its assignee as text and cannot be assigned', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open', 'triage_status' => TriageStatus::Pending]);

    $this->actingAs($admin)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSeeInOrder(['Asignado a', 'Sin asignar'])
        ->assertDontSeeLivewire('tickets.ticket-assignee-selector');

    Livewire::test('tickets.ticket-assignee-selector', ['ticket' => $ticket])
        ->call('assign', (string) $admin->id)
        ->assertForbidden();

    expect($ticket->refresh()->assigned_to)->toBeNull();
});

test('only active admins can be assigned from either view', function (string $component, Closure $assign) {
    $admin = User::factory()->admin()->create();
    $previous = User::factory()->admin()->create();
    $client = User::factory()->create();
    $deletedAdmin = User::factory()->admin()->create();
    $deletedAdmin->delete();
    $ticket = Ticket::factory()->create(['status' => 'open', 'assigned_to' => $previous->id]);

    $this->actingAs($admin);

    foreach ([(string) $client->id, (string) $deletedAdmin->id, '999999'] as $invalidId) {
        $assign(Livewire::test($component, $component === 'tickets.ticket-assignee-selector' ? ['ticket' => $ticket] : []), $ticket, $invalidId)
            ->assertHasNoErrors();

        expect($ticket->refresh()->assigned_to)->toBe($previous->id);
    }
})->with([
    'detalle' => ['tickets.ticket-assignee-selector', fn ($component, $ticket, $id) => $component->call('assign', $id)],
    'listado' => ['tickets.admin-ticket-list', fn ($component, $ticket, $id) => $component->call('assign', $ticket->id, $id)],
]);

test('a deleted admin still holding the ticket shows as a disabled option', function () {
    $admin = User::factory()->admin()->create();
    $formerAgent = User::factory()->admin()->create(['name' => 'Agente Dado De Baja']);
    $ticket = Ticket::factory()->create(['status' => 'open', 'assigned_to' => $formerAgent->id]);
    $formerAgent->delete();

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-assignee-selector', ['ticket' => $ticket])
        ->assertViewHas('formerAssignee', fn ($user) => $user?->id === $formerAgent->id)
        ->assertSee('Agente Dado De Baja');
});
