<?php

use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

test('an admin can access the my-tickets screens', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    $this->get(route('ticket.index'))->assertOk();
    $this->get(route('ticket.create'))->assertOk();
    $this->get(route('ticket.closed'))->assertOk();
});

test('a ticket created by an admin is approved without going through triage', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Necesito acceso a la VPN')
        ->set('description', 'No puedo conectarme a la VPN desde ayer a la tarde.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    $ticket = Ticket::first();

    expect($ticket->user_id)->toBe($admin->id);
    expect($ticket->triage_status)->toBe(TriageStatus::Approved);
});

test('an admin sees their own created tickets on the my-tickets screen', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->create();

    $mine = Ticket::factory()->create(['user_id' => $admin->id, 'title' => 'Mi pedido de admin', 'status' => 'open']);
    Ticket::factory()->create(['user_id' => $other->id, 'title' => 'Ticket de otro usuario', 'status' => 'open']);

    $this->actingAs($admin)
        ->get(route('ticket.index'))
        ->assertOk()
        ->assertSee('Mi pedido de admin')
        ->assertDontSee('Ticket de otro usuario');
});
