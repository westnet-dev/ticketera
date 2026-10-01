<?php

use App\Enums\Level;
use App\Enums\TicketPriority;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('a ticket persists its importance, urgency and impact independently', function () {
    $ticket = Ticket::factory()->create([
        'importance' => Level::High,
        'urgency' => Level::Low,
        'impact' => Level::Medium,
    ])->refresh();

    expect($ticket->importance)->toBe(Level::High);
    expect($ticket->urgency)->toBe(Level::Low);
    expect($ticket->impact)->toBe(Level::Medium);
});

test('a client can create a ticket with importance, urgency and impact and gets the matrix priority', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('tickets.create-ticket')
        ->set('title', 'El servicio está totalmente caído')
        ->set('description', 'No hay conexión en toda la oficina desde hace una hora.')
        ->set('importance', Level::High->value)
        ->set('urgency', Level::Medium->value)
        ->set('impact', Level::Low->value)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    $ticket = Ticket::first();

    expect($ticket->importance)->toBe(Level::High);
    expect($ticket->urgency)->toBe(Level::Medium);
    expect($ticket->impact)->toBe(Level::Low);
    expect($ticket->priority)->toBe(TicketPriority::High);
});

test('creating a ticket with a value outside the levels is rejected', function (string $field, mixed $value) {
    $this->actingAs(User::factory()->create());

    Livewire::test('tickets.create-ticket')
        ->set('title', 'El servicio está totalmente caído')
        ->set('description', 'No hay conexión en toda la oficina desde hace una hora.')
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors([$field]);

    expect(Ticket::count())->toBe(0);
})->with([
    ['importance', 4],
    ['urgency', 7],
    ['impact', 'mucho'],
]);

test('the create form shows the resulting priority as importance and urgency change', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test('tickets.create-ticket')
        ->set('importance', Level::High->value)
        ->set('urgency', Level::Medium->value);

    expect(Str::after($component->html(), 'Prioridad resultante'))->toContain('Alta')->not->toContain('Crítica');

    $component->set('urgency', Level::High->value);

    expect(Str::after($component->html(), 'Prioridad resultante'))->toContain('Crítica');
});

test('the priority is recomputed when the urgency changes', function () {
    $ticket = Ticket::factory()->create(['importance' => Level::High, 'urgency' => Level::Low]);

    expect($ticket->priority)->toBe(TicketPriority::Medium);

    $ticket->update(['urgency' => Level::High]);

    expect($ticket->refresh()->priority)->toBe(TicketPriority::Critical);
});

test('a priority set by hand is replaced by the matrix value', function () {
    $ticket = Ticket::factory()->create(['importance' => Level::Low, 'urgency' => Level::Low]);

    $ticket->priority = TicketPriority::Critical;
    $ticket->save();

    expect($ticket->refresh()->priority)->toBe(TicketPriority::Low);
});

test('the impact does not change the priority level', function () {
    $highImpact = Ticket::factory()->create(['importance' => Level::Medium, 'urgency' => Level::High, 'impact' => Level::High]);
    $lowImpact = Ticket::factory()->create(['importance' => Level::Medium, 'urgency' => Level::High, 'impact' => Level::Low]);

    expect($highImpact->priority)->toBe(TicketPriority::High);
    expect($lowImpact->priority)->toBe(TicketPriority::High);
});

test('sorting by priority breaks ties by impact in the same direction', function (string $direction) {
    $admin = User::factory()->admin()->create();
    $critical = Ticket::factory()->create(['status' => 'open', 'importance' => Level::High, 'urgency' => Level::High, 'impact' => Level::Low]);
    $highWithHighImpact = Ticket::factory()->create(['status' => 'open', 'importance' => Level::High, 'urgency' => Level::Medium, 'impact' => Level::High]);
    $highWithLowImpact = Ticket::factory()->create(['status' => 'open', 'importance' => Level::High, 'urgency' => Level::Medium, 'impact' => Level::Low]);

    $this->actingAs($admin);

    $component = Livewire::test('tickets.admin-ticket-list')->call('sort', 'priority');

    if ($direction === 'desc') {
        $component->call('sort', 'priority');
    }

    $expected = [$critical->id, $highWithHighImpact->id, $highWithLowImpact->id];

    $component->assertViewHas('tickets', fn ($tickets) => $tickets->pluck('id')->all() === ($direction === 'desc' ? $expected : array_reverse($expected)));
})->with(['asc', 'desc']);

test('sorting tickets by urgency descending surfaces the most urgent first', function () {
    $admin = User::factory()->admin()->create();
    $urgent = Ticket::factory()->create(['status' => 'open', 'urgency' => Level::High]);
    $notUrgent = Ticket::factory()->create(['status' => 'open', 'urgency' => Level::Low]);

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')
        ->call('sort', 'urgency')
        ->call('sort', 'urgency')
        ->assertViewHas('tickets', function ($tickets) use ($urgent, $notUrgent) {
            $ids = $tickets->pluck('id')->all();

            return array_search($urgent->id, $ids, true) < array_search($notUrgent->id, $ids, true);
        });
});

test('the ticket detail shows the computed priority and each level', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create([
        'status' => 'open',
        'importance' => Level::High,
        'urgency' => Level::High,
        'impact' => Level::Medium,
    ]);

    $this->actingAs($client)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSeeInOrder(['Prioridad', 'Crítica', 'Importancia', 'Alta', 'Urgencia', 'Alta', 'Impacto', 'Media']);
});

test('the data migration converts 1-10 scores to levels and derives the priority', function () {
    $ticket = Ticket::factory()->create();

    DB::table('tickets')->where('id', $ticket->id)->update(['priority' => 8, 'urgency' => 5, 'impact' => 2]);

    $migration = require database_path('migrations/2026_10_01_122440_convert_ticket_scores_to_priority_matrix.php');
    (fn () => $this->backfill())->call($migration);

    $ticket->refresh();

    expect($ticket->importance)->toBe(Level::High);
    expect($ticket->urgency)->toBe(Level::Medium);
    expect($ticket->impact)->toBe(Level::Low);
    expect($ticket->priority)->toBe(TicketPriority::High);
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

test('a draft saved without levels defaults them to medium', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Falta terminar esto')
        ->set('importance', '')
        ->set('urgency', '')
        ->set('impact', '')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $draft = Ticket::first();

    expect($draft->importance)->toBe(Level::Medium);
    expect($draft->urgency)->toBe(Level::Medium);
    expect($draft->impact)->toBe(Level::Medium);
    expect($draft->priority)->toBe(TicketPriority::Medium);
});

test('editing the importance is recorded in the history as Importancia', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create([
        'status' => 'open',
        'description' => 'Descripción original con suficiente detalle.',
        'importance' => Level::Low,
    ]);

    $this->actingAs($admin);

    Livewire::test('tickets.edit-ticket', ['ticket' => $ticket])
        ->set('importance', Level::High->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($ticket->history()->sole()->to_value)->toBe('importance');

    Livewire::test('tickets.ticket-history-timeline', ['ticket' => $ticket])
        ->assertSee('Importancia');
});
