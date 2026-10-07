<?php

use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

test('an admin can view the tickets list', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('admin.tickets'))
        ->assertOk();
});

test('a client cannot view the admin tickets list', function () {
    $client = User::factory()->create();

    $this->actingAs($client)
        ->get(route('admin.tickets'))
        ->assertForbidden();
});

test('an admin can assign a ticket to another admin user', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')
        ->call('assign', $ticket->id, (string) $otherAdmin->id);

    expect($ticket->refresh()->assigned_to)->toBe($otherAdmin->id);
});

test('an admin can unassign a ticket', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['assigned_to' => $otherAdmin->id]);

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')
        ->call('assign', $ticket->id, '');

    expect($ticket->refresh()->assigned_to)->toBeNull();
});

test('a client cannot assign a ticket', function () {
    $client = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($client);

    Livewire::test('tickets.admin-ticket-list')
        ->call('assign', $ticket->id, (string) $admin->id)
        ->assertForbidden();

    expect($ticket->refresh()->assigned_to)->toBeNull();
});

test('an admin can change a ticket status to any manually-assignable value', function (string $status) {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', $status);

    expect($ticket->refresh()->status)->toBe($status);
})->with(array_diff(Ticket::STATUSES, ['draft']));

test('an admin cannot manually move a ticket back into draft status', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'draft');

    expect($ticket->refresh()->status)->toBe('open');
});

test('a client cannot change a ticket status', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->create(['status' => 'open', 'user_id' => $client->id]);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'paused')
        ->assertForbidden();

    expect($ticket->refresh()->status)->toBe('open');
});

test('a client cannot mark their own ticket as awaiting response', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->create(['status' => 'in_progress', 'user_id' => $client->id]);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'awaiting_response')
        ->assertForbidden();

    expect($ticket->refresh()->status)->toBe('in_progress');
});

test('awaiting response has its own label and color', function () {
    $otherColors = collect(array_diff(Ticket::STATUSES, ['awaiting_response']))
        ->map(fn (string $status): string => Ticket::colorForStatus($status));

    expect(Ticket::labelForStatus('awaiting_response'))->toBe('Esperando respuesta')
        ->and($otherColors)->not->toContain(Ticket::colorForStatus('awaiting_response'));
});

test('pending deploy has its own label and color', function () {
    $otherColors = collect(array_diff(Ticket::STATUSES, ['pending_deploy']))
        ->map(fn (string $status): string => Ticket::colorForStatus($status));

    expect(Ticket::labelForStatus('pending_deploy'))->toBe('Pendiente de subir a producción')
        ->and($otherColors)->not->toContain(Ticket::colorForStatus('pending_deploy'));
});

test('an admin marks a ticket as pending deploy and the history records it', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'in_progress']);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->assertSee('Pendiente de subir a producción')
        ->call('updateStatus', 'pending_deploy');

    $entry = $ticket->history()->where('field', 'status')->latest('id')->first();

    expect($ticket->refresh()->isPendingDeploy())->toBeTrue()
        ->and($entry->from_value)->toBe('in_progress')
        ->and($entry->to_value)->toBe('pending_deploy')
        ->and($entry->user_id)->toBe($admin->id);
});

test('a client cannot mark their own ticket as pending deploy', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->create(['status' => 'in_progress', 'user_id' => $client->id]);

    $this->actingAs($client);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'pending_deploy')
        ->assertForbidden();

    expect($ticket->refresh()->status)->toBe('in_progress');
});

test('a guest cannot change a ticket status', function () {
    $ticket = Ticket::factory()->create(['status' => 'open']);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'paused')
        ->assertForbidden();

    expect($ticket->refresh()->status)->toBe('open');
});

test('the admin ticket list has no status tabs', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')
        ->assertDontSee('Resueltos')
        ->assertDontSee('Cancelados');
});

test('the admin ticket list defaults to the ongoing statuses', function () {
    $admin = User::factory()->admin()->create();
    $openTicket = Ticket::factory()->create(['status' => 'open']);
    $pausedTicket = Ticket::factory()->create(['status' => 'paused']);
    $resolvedTicket = Ticket::factory()->create(['status' => 'resolved']);
    $cancelledTicket = Ticket::factory()->create(['status' => 'cancelled']);

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')
        ->assertViewHas('selectedStatuses', Ticket::ONGOING_STATUSES)
        ->assertSee($openTicket->title)
        ->assertSee($pausedTicket->title)
        ->assertDontSee($resolvedTicket->title)
        ->assertDontSee($cancelledTicket->title);
});

test('an admin can filter the tickets list by several statuses at once', function () {
    $admin = User::factory()->admin()->create();
    $pausedTicket = Ticket::factory()->create(['status' => 'paused']);
    $awaitingTicket = Ticket::factory()->awaitingResponse()->create();
    $openTicket = Ticket::factory()->create(['status' => 'open']);
    $resolvedTicket = Ticket::factory()->create(['status' => 'resolved']);

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')
        ->set('statuses', ['paused', 'awaiting_response'])
        ->assertSee($pausedTicket->title)
        ->assertSee($awaitingTicket->title)
        ->assertDontSee($openTicket->title)
        ->assertDontSee($resolvedTicket->title);
});

test('clearing every status lists every non-draft ticket', function () {
    $admin = User::factory()->admin()->create();
    $openTicket = Ticket::factory()->create(['status' => 'open']);
    $resolvedTicket = Ticket::factory()->create(['status' => 'resolved']);
    $cancelledTicket = Ticket::factory()->create(['status' => 'cancelled']);
    $draftTicket = Ticket::factory()->create(['status' => 'draft']);

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')
        ->call('toggleStatusGroup', 'ongoing')
        ->assertSet('statuses', [])
        ->assertSee($openTicket->title)
        ->assertSee($resolvedTicket->title)
        ->assertSee($cancelledTicket->title)
        ->assertDontSee($draftTicket->title);
});

test('toggling a group ticks it whole, and unticks it once full', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    $component = Livewire::test('tickets.admin-ticket-list')
        ->call('toggleStatus', 'resolved')
        ->call('toggleStatusGroup', 'finished');

    expect($component->get('statuses'))->toEqualCanonicalizing([...Ticket::ONGOING_STATUSES, ...Ticket::FINISHED_STATUSES]);

    $component->call('toggleStatusGroup', 'ongoing');

    expect($component->get('statuses'))->toEqualCanonicalizing(Ticket::FINISHED_STATUSES);
});

test('toggling a single status adds it and removes it', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    $component = Livewire::test('tickets.admin-ticket-list')->call('toggleStatus', 'open');

    expect($component->get('statuses'))->not->toContain('open');

    $component->call('toggleStatus', 'open');

    expect($component->get('statuses'))->toContain('open');
});

test('the admin status filter rejects values it does not offer', function (string $value) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')
        ->call('toggleStatus', $value)
        ->call('toggleStatusGroup', $value)
        ->assertSet('statuses', null)
        ->assertViewHas('selectedStatuses', Ticket::ONGOING_STATUSES);
})->with(['draft', 'pending_validation', 'not-a-real-status']);

test('tampered statuses in the url are ignored', function () {
    $admin = User::factory()->admin()->create();
    $openTicket = Ticket::factory()->create(['status' => 'open']);
    $pausedTicket = Ticket::factory()->create(['status' => 'paused']);
    $draftTicket = Ticket::factory()->create(['status' => 'draft']);

    $this->actingAs($admin);

    Livewire::withQueryParams(['statuses' => ['open', 'bogus', 'draft']])
        ->test('tickets.admin-ticket-list')
        ->assertViewHas('selectedStatuses', ['open'])
        ->assertSee($openTicket->title)
        ->assertDontSee($pausedTicket->title)
        ->assertDontSee($draftTicket->title);
});

test('a url with only invalid statuses falls back to the default', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::withQueryParams(['statuses' => ['bogus']])
        ->test('tickets.admin-ticket-list')
        ->assertViewHas('selectedStatuses', Ticket::ONGOING_STATUSES);
});

test('the unassigned view lives in the assignee filter', function () {
    $admin = User::factory()->admin()->create();
    $unassignedTicket = Ticket::factory()->create(['status' => 'open', 'assigned_to' => null]);
    $assignedTicket = Ticket::factory()->create(['status' => 'open', 'assigned_to' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')
        ->set('assignedToFilter', 'unassigned')
        ->assertSee($unassignedTicket->title)
        ->assertDontSee($assignedTicket->title);
});

test('an invalid status value is rejected', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'not-a-real-status');

    expect($ticket->refresh()->status)->toBe('open');
});
