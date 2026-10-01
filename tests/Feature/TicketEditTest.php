<?php

use App\Enums\Level;
use App\Enums\TicketPriority;
use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

/**
 * @return array{title: string, description: string, importance: int, urgency: int, impact: int}
 */
function validTicketEdit(): array
{
    return [
        'title' => 'Título editado y más descriptivo',
        'description' => 'Descripción editada con más detalle sobre el problema.',
        'importance' => Level::High->value,
        'urgency' => Level::High->value,
        'impact' => Level::Medium->value,
    ];
}

function editTicket(Ticket $ticket, array $overrides = []): Testable
{
    $component = Livewire::test('tickets.edit-ticket', ['ticket' => $ticket]);

    foreach ([...validTicketEdit(), ...$overrides] as $property => $value) {
        $component->set($property, $value);
    }

    return $component->call('save');
}

test('an admin can edit an approved ticket from a client without changing its state', function () {
    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->admin()->create();
    $client = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'user_id' => $client->id,
        'status' => 'in_progress',
        'triage_status' => TriageStatus::Approved,
        'assigned_to' => $assignee->id,
    ]);

    $this->actingAs($admin);

    editTicket($ticket)
        ->assertHasNoErrors()
        ->assertRedirect(route('ticket.show', $ticket));

    $ticket->refresh();

    expect($ticket->title)->toBe('Título editado y más descriptivo');
    expect($ticket->importance)->toBe(Level::High);
    expect($ticket->priority)->toBe(TicketPriority::Critical);
    expect($ticket->triage_status)->toBe(TriageStatus::Approved);
    expect($ticket->status)->toBe('in_progress');
    expect($ticket->user_id)->toBe($client->id);
    expect($ticket->assigned_to)->toBe($assignee->id);
});

test('an admin can edit tickets in any triage or status', function (string $triageStatus, string $status) {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['triage_status' => $triageStatus, 'status' => $status]);

    $this->actingAs($admin);

    editTicket($ticket)->assertHasNoErrors();

    $ticket->refresh();

    expect($ticket->description)->toBe('<p>Descripción editada con más detalle sobre el problema.</p>');
    expect($ticket->triage_status->value)->toBe($triageStatus);
    expect($ticket->status)->toBe($status);
})->with([
    'pending' => [TriageStatus::Pending->value, 'open'],
    'rejected' => [TriageStatus::Rejected->value, 'open'],
    'resolved' => [TriageStatus::Approved->value, 'resolved'],
    'cancelled' => [TriageStatus::Approved->value, 'cancelled'],
]);

test('a client can edit their own pending ticket and it stays pending', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $client->id, 'triage_status' => TriageStatus::Pending, 'status' => 'open']);

    $this->actingAs($client);

    editTicket($ticket)->assertHasNoErrors();

    $ticket->refresh();

    expect($ticket->title)->toBe('Título editado y más descriptivo');
    expect($ticket->triage_status)->toBe(TriageStatus::Pending);
});

test('a client cannot edit their own ticket once it left pending triage', function (string $triageStatus) {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $client->id, 'triage_status' => $triageStatus, 'title' => 'Título original']);

    $this->actingAs($client);

    editTicket($ticket)->assertForbidden();

    expect($ticket->fresh()->title)->toBe('Título original');
})->with([TriageStatus::Approved->value, TriageStatus::Rejected->value]);

test('a client cannot edit someone else\'s ticket', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $owner->id, 'triage_status' => TriageStatus::Pending, 'title' => 'Título original']);

    $this->actingAs($other);

    editTicket($ticket)->assertForbidden();

    expect($ticket->fresh()->title)->toBe('Título original');
});

test('drafts cannot be edited through the ticket edit action, not even by admins', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $draft = Ticket::factory()->draft()->create(['user_id' => $owner->id]);

    $this->actingAs($admin);

    editTicket($draft)->assertForbidden();

    $this->actingAs($owner);

    editTicket($draft)->assertForbidden();
});

test('a client cannot save an edit if the ticket got approved while editing', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $client->id, 'triage_status' => TriageStatus::Pending, 'title' => 'Título original']);

    $this->actingAs($client);

    $component = Livewire::test('tickets.edit-ticket', ['ticket' => $ticket])
        ->set('title', 'Título editado y más descriptivo');

    Ticket::whereKey($ticket->id)->update(['triage_status' => TriageStatus::Approved]);

    $component->call('save')->assertForbidden();

    expect($ticket->fresh()->title)->toBe('Título original');
});

test('edits are validated with the same rules as ticket creation', function (string $field, mixed $value) {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['title' => 'Título original']);

    $this->actingAs($admin);

    editTicket($ticket, [$field => $value])->assertHasErrors([$field]);

    expect($ticket->fresh()->title)->toBe('Título original');
})->with([
    'short title' => ['title', 'Hola'],
    'short description' => ['description', 'Corta'],
    'importance out of range' => ['importance', 4],
    'urgency out of range' => ['urgency', 0],
    'impact not a level' => ['impact', 'mucho'],
]);

test('an edit can remove an attached image, deleting its record and file', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();
    Storage::disk('public')->put('tickets/vieja.jpg', 'contenido');
    $image = $ticket->images()->create(['image_path' => 'tickets/vieja.jpg']);

    $this->actingAs($admin);

    editTicket($ticket, ['imagesToRemove' => [$image->id]])->assertHasNoErrors();

    expect(TicketImage::find($image->id))->toBeNull();
    Storage::disk('public')->assertMissing('tickets/vieja.jpg');
});

test('an edit can add new images', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $client->id, 'triage_status' => TriageStatus::Pending]);
    $ticket->images()->create(['image_path' => 'tickets/existente.jpg']);

    $this->actingAs($client);

    editTicket($ticket, ['newImages' => [UploadedFile::fake()->image('nueva.jpg')]])->assertHasNoErrors();

    expect($ticket->images()->count())->toBe(2);
    Storage::disk('public')->assertExists($ticket->images()->latest('id')->first()->image_path);
});

test('an edit cannot leave the ticket with more than five images', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['title' => 'Título original']);

    foreach (range(1, 4) as $i) {
        $ticket->images()->create(['image_path' => "tickets/existente-{$i}.jpg"]);
    }

    $this->actingAs($admin);

    editTicket($ticket, ['newImages' => [
        UploadedFile::fake()->image('nueva-1.jpg'),
        UploadedFile::fake()->image('nueva-2.jpg'),
    ]])->assertHasErrors(['newImages']);

    expect($ticket->images()->count())->toBe(4);
    expect($ticket->fresh()->title)->toBe('Título original');
});

test('an edit cannot remove an image that belongs to another ticket', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();
    $otherImage = Ticket::factory()->create()->images()->create(['image_path' => 'tickets/ajena.jpg']);

    $this->actingAs($admin);

    editTicket($ticket, ['imagesToRemove' => [$otherImage->id]])->assertHasNoErrors();

    expect(TicketImage::find($otherImage->id))->not->toBeNull();
});

test('an edit that changes details records a single history entry naming the changed fields', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['urgency' => Level::Low]);

    $this->actingAs($admin);

    editTicket($ticket, [
        'description' => $ticket->description,
        'importance' => $ticket->importance->value,
        'impact' => $ticket->impact->value,
    ])->assertHasNoErrors();

    $entry = TicketHistory::where('ticket_id', $ticket->id)->sole();

    expect($entry->field)->toBe('details');
    expect($entry->user_id)->toBe($admin->id);
    expect($entry->to_value)->toBe('title,urgency');
});

test('saving an edit without changes does not record history', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['description' => 'Descripción original con suficiente detalle.']);

    $this->actingAs($admin);

    Livewire::test('tickets.edit-ticket', ['ticket' => $ticket])
        ->call('save')
        ->assertHasNoErrors();

    expect(TicketHistory::where('ticket_id', $ticket->id)->exists())->toBeFalse();
});

test('the history timeline labels details edits', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();
    TicketHistory::create([
        'ticket_id' => $ticket->id,
        'user_id' => $admin->id,
        'field' => 'details',
        'from_value' => null,
        'to_value' => 'title,images',
    ]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-history-timeline', ['ticket' => $ticket])
        ->assertSee(__('Detalles editados'))
        ->assertSee('Título, Imágenes');
});

test('the edit action is shown to admins and to the author of a pending ticket', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $client->id, 'triage_status' => TriageStatus::Pending, 'status' => 'open']);

    $this->actingAs($admin)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSee(__('Editar ticket'));

    $this->actingAs($client)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSee(__('Editar ticket'));
});

test('the edit action is hidden from the author of an approved ticket', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $client->id, 'triage_status' => TriageStatus::Approved, 'status' => 'open']);

    $this->actingAs($client)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertDontSee(__('Editar ticket'));
});
