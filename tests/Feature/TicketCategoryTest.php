<?php

use App\Enums\Level;
use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketHistory;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function fillNewTicket(): Testable
{
    return Livewire::test('tickets.create-ticket')
        ->set('title', 'No funciona la VPN')
        ->set('description', 'No puedo conectarme a la VPN desde ayer a la tarde.')
        ->set('importance', Level::Medium->value)
        ->set('urgency', Level::Medium->value)
        ->set('impact', Level::Medium->value);
}

test('a client can create a ticket with a category', function () {
    $category = TicketCategory::where('name', 'Error')->first();

    $this->actingAs(User::factory()->create());

    fillNewTicket()
        ->set('category_id', $category->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::first()->category_id)->toBe($category->id);
});

test('a client can create a ticket without a category', function () {
    $this->actingAs(User::factory()->create());

    fillNewTicket()
        ->set('category_id', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::first()->category_id)->toBeNull();
});

test('a ticket cannot be created with a category that does not exist', function () {
    $this->actingAs(User::factory()->create());

    fillNewTicket()
        ->set('category_id', 999999)
        ->call('save')
        ->assertHasErrors(['category_id']);

    expect(Ticket::count())->toBe(0);
});

test('a draft keeps its category', function () {
    $category = TicketCategory::where('name', 'Consulta')->first();

    $this->actingAs(User::factory()->create());

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Falta terminar esto')
        ->set('category_id', $category->id)
        ->call('saveDraft')
        ->assertHasNoErrors();

    $draft = Ticket::first();

    expect($draft->category_id)->toBe($category->id);

    Livewire::test('tickets.create-ticket', ['draft' => $draft])
        ->assertSet('category_id', $category->id);
});

test('the category field is hidden when there are no categories', function () {
    TicketCategory::query()->delete();

    $this->actingAs(User::factory()->create());

    fillNewTicket()
        ->assertDontSee('Sin categoría')
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::first()->category_id)->toBeNull();
});

test('the author can change the category of a pending ticket and it is recorded in the history', function () {
    $client = User::factory()->create();
    $from = TicketCategory::where('name', 'Consulta')->first();
    $to = TicketCategory::where('name', 'Error')->first();
    $ticket = Ticket::factory()->create([
        'user_id' => $client->id,
        'description' => 'Descripción original con suficiente detalle.',
        'triage_status' => TriageStatus::Pending,
        'category_id' => $from->id,
    ]);

    $this->actingAs($client);

    Livewire::test('tickets.edit-ticket', ['ticket' => $ticket])
        ->assertSet('category_id', $from->id)
        ->set('category_id', $to->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($ticket->refresh()->category_id)->toBe($to->id);

    $entry = TicketHistory::where('ticket_id', $ticket->id)->where('field', 'details')->first();

    expect($entry->to_value)->toBe('category_id');
});

test('an admin can set the category of a ticket pending triage', function () {
    $admin = User::factory()->admin()->create();
    $category = TicketCategory::where('name', 'Nueva funcionalidad')->first();
    $ticket = Ticket::factory()->create([
        'description' => 'Descripción original con suficiente detalle.',
        'triage_status' => TriageStatus::Pending,
    ]);

    $this->actingAs($admin);

    Livewire::test('tickets.edit-ticket', ['ticket' => $ticket])
        ->set('category_id', $category->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($ticket->refresh()->category_id)->toBe($category->id);
});

test('the author can clear the category when resubmitting a rejected ticket', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'user_id' => $client->id,
        'description' => 'Descripción original con suficiente detalle.',
        'triage_status' => TriageStatus::Rejected,
        'category_id' => TicketCategory::first()->id,
    ]);

    $this->actingAs($client);

    Livewire::test('tickets.revise-ticket', ['ticket' => $ticket])
        ->set('category_id', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($ticket->refresh()->category_id)->toBeNull();
});

test('the ticket detail and listing show the category or its absence', function () {
    $client = User::factory()->create();
    $category = TicketCategory::factory()->create(['name' => 'Infraestructura']);
    $categorized = Ticket::factory()->create(['user_id' => $client->id, 'status' => 'open', 'category_id' => $category->id]);
    Ticket::factory()->create(['user_id' => $client->id, 'status' => 'open', 'category_id' => null]);

    $this->actingAs($client);

    $this->get(route('ticket.show', $categorized))
        ->assertOk()
        ->assertSee('Infraestructura');

    Livewire::test('tickets.ticket-list')
        ->assertSee('Infraestructura')
        ->assertSee('Sin categoría');
});
