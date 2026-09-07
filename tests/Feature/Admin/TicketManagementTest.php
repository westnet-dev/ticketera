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

test('a guest cannot change a ticket status', function () {
    $ticket = Ticket::factory()->create(['status' => 'open']);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'paused')
        ->assertForbidden();

    expect($ticket->refresh()->status)->toBe('open');
});

test('an invalid status value is rejected', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'not-a-real-status');

    expect($ticket->refresh()->status)->toBe('open');
});
