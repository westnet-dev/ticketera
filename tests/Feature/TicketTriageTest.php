<?php

use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\TicketImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('a ticket created by a client starts pending triage', function () {
    $client = User::factory()->create();

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'El servicio está totalmente caído')
        ->set('description', 'No hay conexión en toda la oficina desde hace una hora.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::first()->triage_status)->toBe(TriageStatus::Pending);
});

test('pending and rejected tickets are excluded from the general admin ticket list, approved ones are included', function () {
    $admin = User::factory()->admin()->create();
    $pending = Ticket::factory()->create(['triage_status' => TriageStatus::Pending]);
    $rejected = Ticket::factory()->create(['triage_status' => TriageStatus::Rejected]);
    $approved = Ticket::factory()->create(['triage_status' => TriageStatus::Approved]);

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')
        ->assertViewHas('tickets', function ($tickets) use ($pending, $rejected, $approved) {
            $ids = $tickets->pluck('id')->all();

            return ! in_array($pending->id, $ids, true)
                && ! in_array($rejected->id, $ids, true)
                && in_array($approved->id, $ids, true);
        });
});

test('an admin can approve a pending ticket from the triage module', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['triage_status' => TriageStatus::Pending]);

    $this->actingAs($admin);

    Livewire::test('tickets.admin-triage-list')
        ->call('approve', $ticket->id);

    expect($ticket->refresh()->triage_status)->toBe(TriageStatus::Approved);
});

test('an admin can reject a pending ticket with a reason, visible in the ticket chat', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['triage_status' => TriageStatus::Pending]);

    $this->actingAs($admin);

    Livewire::test('tickets.admin-triage-list')
        ->call('startRejecting', $ticket->id)
        ->set('rejectionReason', 'Falta más información sobre el problema.')
        ->call('reject')
        ->assertHasNoErrors();

    expect($ticket->refresh()->triage_status)->toBe(TriageStatus::Rejected);
    expect($ticket->messages()->where('body', 'Falta más información sobre el problema.')->exists())->toBeTrue();
});

test('rejecting without a reason fails validation and does not change triage status', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['triage_status' => TriageStatus::Pending]);

    $this->actingAs($admin);

    Livewire::test('tickets.admin-triage-list')
        ->call('startRejecting', $ticket->id)
        ->set('rejectionReason', '')
        ->call('reject')
        ->assertHasErrors(['rejectionReason']);

    expect($ticket->refresh()->triage_status)->toBe(TriageStatus::Pending);
});

test('a client cannot access the triage module or its actions', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->create(['triage_status' => TriageStatus::Pending]);

    $this->actingAs($client)
        ->get(route('admin.triage'))
        ->assertForbidden();

    Livewire::test('tickets.admin-triage-list')
        ->call('approve', $ticket->id)
        ->assertForbidden();

    Livewire::test('tickets.admin-triage-list')
        ->call('startRejecting', $ticket->id)
        ->assertForbidden();
});

test('the creator of a rejected ticket can revise and resubmit it', function () {
    $owner = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'user_id' => $owner->id,
        'triage_status' => TriageStatus::Rejected,
        'title' => 'Título viejo',
    ]);
    $ticket->images()->create(['image_path' => 'images/tickets/existing.jpg']);

    $this->actingAs($owner);

    Livewire::test('tickets.revise-ticket', ['ticket' => $ticket])
        ->set('title', 'Título corregido y más descriptivo')
        ->set('description', 'Descripción corregida con más detalle sobre el problema.')
        ->set('priority', 7)
        ->set('urgency', 6)
        ->set('impact', 5)
        ->call('save')
        ->assertHasNoErrors();

    $ticket->refresh();

    expect($ticket->title)->toBe('Título corregido y más descriptivo');
    expect($ticket->triage_status)->toBe(TriageStatus::Pending);
});

test('the revise form disappears after a successful resubmission, preventing a duplicate submit', function () {
    $owner = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $owner->id, 'triage_status' => TriageStatus::Rejected]);
    $ticket->images()->create(['image_path' => 'images/tickets/existing.jpg']);

    $this->actingAs($owner);

    Livewire::test('tickets.revise-ticket', ['ticket' => $ticket])
        ->set('title', 'Título corregido y más descriptivo')
        ->set('description', 'Descripción corregida con más detalle sobre el problema.')
        ->set('priority', 7)
        ->set('urgency', 6)
        ->set('impact', 5)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDontSee(__('Reenviar a triage'))
        ->call('save')
        ->assertForbidden();
});

test('a ticket cannot be revised unless it is rejected', function (string $triageStatus) {
    $owner = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $owner->id, 'triage_status' => $triageStatus]);

    $this->actingAs($owner);

    Livewire::test('tickets.revise-ticket', ['ticket' => $ticket])
        ->call('save')
        ->assertForbidden();
})->with([TriageStatus::Pending->value, TriageStatus::Approved->value]);

test('another user cannot revise someone else\'s rejected ticket', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $owner->id, 'triage_status' => TriageStatus::Rejected]);

    $this->actingAs($other);

    Livewire::test('tickets.revise-ticket', ['ticket' => $ticket])
        ->call('save')
        ->assertForbidden();
});

test('assigning or changing the status of a non-approved ticket is forbidden', function (string $triageStatus) {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['triage_status' => $triageStatus]);

    $this->actingAs($admin);

    Livewire::test('tickets.admin-ticket-list')
        ->call('assign', $ticket->id, (string) $otherAdmin->id)
        ->assertForbidden();

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'in_progress')
        ->assertForbidden();
})->with([TriageStatus::Pending->value, TriageStatus::Rejected->value]);

test('the creator of a rejected ticket can remove an existing image when resubmitting', function () {
    Storage::fake('public');

    $owner = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $owner->id, 'triage_status' => TriageStatus::Rejected]);

    $removedPath = UploadedFile::fake()->image('foto.jpg')->store('tickets', 'public');
    $removedImage = $ticket->images()->create(['image_path' => $removedPath]);
    $ticket->images()->create(['image_path' => UploadedFile::fake()->image('foto2.jpg')->store('tickets', 'public')]);

    $this->actingAs($owner);

    Livewire::test('tickets.revise-ticket', ['ticket' => $ticket])
        ->set('title', 'Título corregido y más descriptivo')
        ->set('description', 'Descripción corregida con más detalle sobre el problema.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('imagesToRemove', [$removedImage->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(TicketImage::find($removedImage->id))->toBeNull();
    Storage::disk('public')->assertMissing($removedPath);
    expect($ticket->images()->count())->toBe(1);
});

test('the creator of a rejected ticket can add a new image when resubmitting', function () {
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
        ->set('newImages', [UploadedFile::fake()->image('nueva.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    expect($ticket->images()->count())->toBe(2);
});

test('resubmitting a ticket cannot leave it with zero images', function () {
    $owner = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $owner->id, 'triage_status' => TriageStatus::Rejected]);
    $image = $ticket->images()->create(['image_path' => 'images/tickets/unica.jpg']);

    $this->actingAs($owner);

    Livewire::test('tickets.revise-ticket', ['ticket' => $ticket])
        ->set('title', 'Título corregido y más descriptivo')
        ->set('description', 'Descripción corregida con más detalle sobre el problema.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('imagesToRemove', [$image->id])
        ->call('save')
        ->assertHasErrors(['newImages']);

    expect(TicketImage::find($image->id))->not->toBeNull();
    expect($ticket->refresh()->triage_status)->toBe(TriageStatus::Rejected);
});

test('resubmitting a ticket cannot exceed 5 images in total', function () {
    $owner = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $owner->id, 'triage_status' => TriageStatus::Rejected]);

    collect(range(1, 5))->each(fn ($i) => $ticket->images()->create(['image_path' => "images/tickets/existing-{$i}.jpg"]));

    $this->actingAs($owner);

    Livewire::test('tickets.revise-ticket', ['ticket' => $ticket])
        ->set('title', 'Título corregido y más descriptivo')
        ->set('description', 'Descripción corregida con más detalle sobre el problema.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('newImages', [UploadedFile::fake()->image('extra.jpg')])
        ->call('save')
        ->assertHasErrors(['newImages']);

    expect($ticket->images()->count())->toBe(5);
    expect($ticket->refresh()->triage_status)->toBe(TriageStatus::Rejected);
});

test('removing an image id that belongs to another ticket does not delete it', function () {
    $owner = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $owner->id, 'triage_status' => TriageStatus::Rejected]);
    $ticket->images()->create(['image_path' => 'images/tickets/mine.jpg']);

    $otherTicket = Ticket::factory()->create();
    $foreignImage = $otherTicket->images()->create(['image_path' => 'images/tickets/foreign.jpg']);

    $this->actingAs($owner);

    Livewire::test('tickets.revise-ticket', ['ticket' => $ticket])
        ->set('title', 'Título corregido y más descriptivo')
        ->set('description', 'Descripción corregida con más detalle sobre el problema.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('imagesToRemove', [$foreignImage->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(TicketImage::find($foreignImage->id))->not->toBeNull();
});
