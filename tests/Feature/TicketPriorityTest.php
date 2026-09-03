<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

test('a ticket persists its priority, urgency and impact independently', function () {
    $ticket = Ticket::factory()->create(['priority' => 7, 'urgency' => 3, 'impact' => 10]);

    expect($ticket->priority)->toBe(7);
    expect($ticket->urgency)->toBe(3);
    expect($ticket->impact)->toBe(10);
});

test('a client can create a ticket with priority, urgency and impact', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'El servicio está totalmente caído')
        ->set('description', 'No hay conexión en toda la oficina desde hace una hora.')
        ->set('priority', 9)
        ->set('urgency', 10)
        ->set('impact', 6)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    $ticket = Ticket::first();

    expect($ticket->priority)->toBe(9);
    expect($ticket->urgency)->toBe(10);
    expect($ticket->impact)->toBe(6);
});

test('creating a ticket with an out-of-range priority, urgency or impact is rejected', function (string $field) {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'El servicio está totalmente caído')
        ->set('description', 'No hay conexión en toda la oficina desde hace una hora.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set($field, 11)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertHasErrors([$field]);

    expect(Ticket::count())->toBe(0);
})->with(['priority', 'urgency', 'impact']);

test('sorting tickets by priority descending surfaces the highest priority first', function () {
    $admin = User::factory()->admin()->create();
    $high = Ticket::factory()->create(['priority' => 9]);
    $low = Ticket::factory()->create(['priority' => 2]);

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')
        ->call('sort', 'priority')
        ->call('sort', 'priority')
        ->assertViewHas('tickets', function ($tickets) use ($high, $low) {
            $ids = $tickets->pluck('id')->all();

            return array_search($high->id, $ids, true) < array_search($low->id, $ids, true);
        });
});

test('sorting tickets by urgency descending surfaces the most urgent first', function () {
    $admin = User::factory()->admin()->create();
    $urgent = Ticket::factory()->create(['urgency' => 10]);
    $notUrgent = Ticket::factory()->create(['urgency' => 1]);

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')
        ->call('sort', 'urgency')
        ->call('sort', 'urgency')
        ->assertViewHas('tickets', function ($tickets) use ($urgent, $notUrgent) {
            $ids = $tickets->pluck('id')->all();

            return array_search($urgent->id, $ids, true) < array_search($notUrgent->id, $ids, true);
        });
});

test('the legacy priority migration maps each category to the expected numeric value', function () {
    $migration = require database_path('migrations/2026_09_03_115852_convert_tickets_priority_to_numeric_scale_and_add_urgency_impact.php');

    $map = (new ReflectionClass($migration))->getConstant('LEGACY_PRIORITY_MAP');

    expect($map)->toBe([
        'low' => 2,
        'medium' => 5,
        'high' => 8,
        'urgent' => 10,
    ]);
});
