<?php

use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Livewire\Livewire;

/**
 * Collaborators are admins working on a ticket besides its assignee. Admins
 * manage them under the same rules as the assignment.
 */
function collaboratorEntries(Ticket $ticket)
{
    return TicketHistory::where('ticket_id', $ticket->id)->where('field', 'collaborators')->orderBy('id')->get();
}

test('an admin adds a collaborator from the ticket detail and the history records it', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->admin()->create(['name' => 'Agente Ficticio']);
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($admin)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSee('Colaboradores')
        ->assertSeeLivewire('tickets.ticket-collaborators');

    Livewire::test('tickets.ticket-collaborators', ['ticket' => $ticket])
        ->call('addCollaborator', (string) $agent->id)
        ->assertSee('Agente Ficticio');

    $entry = collaboratorEntries($ticket)->sole();

    expect($ticket->collaborators()->pluck('users.id')->all())->toBe([$agent->id])
        ->and($entry->from_value)->toBeNull()
        ->and($entry->to_value)->toBe((string) $agent->id)
        ->and($entry->user_id)->toBe($admin->id);
});

test('an admin removes a collaborator and the history records it', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);
    $ticket->collaborators()->attach($agent->id);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-collaborators', ['ticket' => $ticket])
        ->call('removeCollaborator', $agent->id);

    $entry = collaboratorEntries($ticket)->sole();

    expect($ticket->collaborators()->count())->toBe(0)
        ->and($entry->from_value)->toBe((string) $agent->id)
        ->and($entry->to_value)->toBeNull();
});

test('adding someone who already collaborates changes nothing', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-collaborators', ['ticket' => $ticket])
        ->call('addCollaborator', (string) $agent->id)
        ->call('addCollaborator', (string) $agent->id)
        ->call('removeCollaborator', $admin->id);

    expect($ticket->collaborators()->count())->toBe(1)
        ->and(collaboratorEntries($ticket))->toHaveCount(1);
});

test('the add options leave out the assignee and the current collaborators', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin Asignado']);
    $collaborator = User::factory()->admin()->create(['name' => 'Ya Colabora']);
    $candidate = User::factory()->admin()->create(['name' => 'Puede Sumarse']);
    $ticket = Ticket::factory()->create(['status' => 'open', 'assigned_to' => $admin->id]);
    $ticket->collaborators()->attach($collaborator->id);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-collaborators', ['ticket' => $ticket])
        ->assertViewHas('candidates', fn ($candidates) => $candidates->modelKeys() === [$candidate->id]);
});

test('an unassigned ticket offers every active admin', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open', 'assigned_to' => null]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-collaborators', ['ticket' => $ticket])
        ->assertViewHas('candidates', fn ($candidates) => collect($candidates->modelKeys())->sort()->values()->all() === collect([$admin->id, $other->id])->sort()->values()->all());
});

test('only active admins other than the assignee can be added', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->create();
    $deletedAdmin = User::factory()->admin()->create();
    $deletedAdmin->delete();
    $ticket = Ticket::factory()->create(['status' => 'open', 'assigned_to' => $admin->id]);

    $this->actingAs($admin);

    foreach ([(string) $client->id, (string) $deletedAdmin->id, '999999', (string) $admin->id, ''] as $invalidId) {
        Livewire::test('tickets.ticket-collaborators', ['ticket' => $ticket])
            ->call('addCollaborator', $invalidId)
            ->assertHasNoErrors();
    }

    expect($ticket->collaborators()->count())->toBe(0)
        ->and(collaboratorEntries($ticket))->toBeEmpty();
});

test('a client sees no collaborators field and cannot manage them', function () {
    $client = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->for($client)->create(['status' => 'open']);
    $ticket->collaborators()->attach($admin->id);

    $this->actingAs($client)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertDontSee('Colaboradores')
        ->assertDontSeeLivewire('tickets.ticket-collaborators');

    Livewire::test('tickets.ticket-collaborators', ['ticket' => $ticket])
        ->call('addCollaborator', (string) User::factory()->admin()->create()->id)
        ->assertForbidden();

    Livewire::test('tickets.ticket-collaborators', ['ticket' => $ticket])
        ->call('removeCollaborator', $admin->id)
        ->assertForbidden();

    expect($ticket->collaborators()->pluck('users.id')->all())->toBe([$admin->id]);
});

test('a ticket pending triage lists its collaborators read-only', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->admin()->create(['name' => 'Agente Ficticio']);
    $ticket = Ticket::factory()->create(['status' => 'open', 'triage_status' => TriageStatus::Pending]);
    $ticket->collaborators()->attach($agent->id);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-collaborators', ['ticket' => $ticket])
        ->assertSee('Agente Ficticio')
        ->assertDontSee('Agregar colaborador')
        ->assertDontSeeHtml('removeCollaborator')
        ->call('removeCollaborator', $agent->id)
        ->assertForbidden();

    expect($ticket->collaborators()->count())->toBe(1);
});

test('assigning a collaborator takes them off the collaborators', function (string $view) {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);
    $ticket->collaborators()->attach($agent->id);

    $this->actingAs($admin);

    $view === 'detalle'
        ? Livewire::test('tickets.ticket-assignee-selector', ['ticket' => $ticket])
            ->call('assign', (string) $agent->id)
            ->assertDispatched('ticket-assignee-changed')
        : Livewire::test('tickets.admin-ticket-list')->call('assign', $ticket->id, (string) $agent->id);

    expect($ticket->refresh()->assigned_to)->toBe($agent->id)
        ->and($ticket->collaborators()->count())->toBe(0)
        ->and(TicketHistory::where('ticket_id', $ticket->id)->where('field', 'assigned_to')->exists())->toBeTrue()
        ->and(collaboratorEntries($ticket)->sole()->from_value)->toBe((string) $agent->id);
})->with(['detalle', 'listado']);

test('the collaborators list refreshes when the assignee changes', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($admin);

    $collaborators = Livewire::test('tickets.ticket-collaborators', ['ticket' => $ticket])
        ->assertViewHas('candidates', fn ($candidates) => in_array($agent->id, $candidates->modelKeys(), true));

    $ticket->assignTo($agent);

    $collaborators->dispatch('ticket-assignee-changed')
        ->assertViewHas('candidates', fn ($candidates) => ! in_array($agent->id, $candidates->modelKeys(), true));
});

test('a deleted admin stays listed as collaborator until removed', function () {
    $admin = User::factory()->admin()->create();
    $formerAgent = User::factory()->admin()->create(['name' => 'Agente Dado De Baja']);
    $ticket = Ticket::factory()->create(['status' => 'open']);
    $ticket->collaborators()->attach($formerAgent->id);
    $formerAgent->delete();

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-collaborators', ['ticket' => $ticket])
        ->assertSee('Agente Dado De Baja')
        ->call('removeCollaborator', $formerAgent->id);

    expect($ticket->collaborators()->count())->toBe(0);
});

test('the history shows who was added and removed as collaborator', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->admin()->create(['name' => 'Agente Ficticio']);
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($admin);

    $ticket->addCollaborator($agent);
    $ticket->removeCollaborator($agent);

    Livewire::test('tickets.ticket-history-timeline', ['ticket' => $ticket])
        ->assertSeeInOrder(['Colaboradores', 'se agregó a Agente Ficticio', 'Colaboradores', 'se quitó a Agente Ficticio']);
});

test('the assigned tab includes collaborations once each, marked as such', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $assigned = Ticket::factory()->create(['status' => 'in_progress', 'assigned_to' => $admin->id, 'title' => 'Ticket asignado a mí']);
    $collaboration = Ticket::factory()->create(['status' => 'open', 'assigned_to' => $otherAdmin->id, 'title' => 'Ticket donde colaboro']);
    $collaboration->collaborators()->attach([$admin->id, $otherAdmin->id]);
    $draft = Ticket::factory()->draft()->create();
    $draft->collaborators()->attach($admin->id);
    Ticket::factory()->create(['status' => 'open', 'assigned_to' => $otherAdmin->id]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-list')
        ->assertViewHas('tabs', fn ($tabs) => $tabs['assigned']['count'] === 2)
        ->call('selectTab', 'assigned')
        ->assertViewHas('tickets', fn ($tickets) => collect($tickets->pluck('id'))->sort()->values()->all() === collect([$assigned->id, $collaboration->id])->sort()->values()->all()
            && $tickets->firstWhere('id', $collaboration->id)->is_collaborator
            && ! $tickets->firstWhere('id', $assigned->id)->is_collaborator)
        ->assertSeeInOrder(['Ticket donde colaboro', 'Colaborador']);
});
