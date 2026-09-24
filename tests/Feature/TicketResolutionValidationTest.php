<?php

use App\Enums\ValidationStatus;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

test('resolving a ticket asks its author to validate the resolution', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'in_progress']);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'resolved');

    $ticket->refresh();

    expect($ticket->status)->toBe('resolved');
    expect($ticket->validation_status)->toBe(ValidationStatus::Pending);
});

test('cancelling a ticket does not ask its author to validate anything', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'in_progress']);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'cancelled');

    $ticket->refresh();

    expect($ticket->status)->toBe('cancelled');
    expect($ticket->validation_status)->toBe(ValidationStatus::NotRequested);
});

test('taking a ticket out of resolved before the author answers withdraws the validation request', function (string $status) {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->awaitingValidation()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', $status);

    $ticket->refresh();

    expect($ticket->status)->toBe($status);
    expect($ticket->validation_status)->toBe(ValidationStatus::NotRequested);
})->with(['in_progress', 'paused', 'cancelled']);

test('the author confirms the resolution with a rating', function () {
    $owner = User::factory()->create();
    $ticket = Ticket::factory()->awaitingValidation()->create(['user_id' => $owner->id]);

    $this->actingAs($owner);

    Livewire::test('tickets.ticket-validation-actions', ['ticket' => $ticket])
        ->set('rating', 4)
        ->call('confirm')
        ->assertHasNoErrors();

    $ticket->refresh();

    expect($ticket->validation_status)->toBe(ValidationStatus::Confirmed);
    expect($ticket->resolution_rating)->toBe(4);
    expect($ticket->validated_at)->not->toBeNull();
    expect($ticket->status)->toBe('resolved');
});

test('confirming without a valid rating fails validation and leaves the ticket pending', function (?int $rating) {
    $owner = User::factory()->create();
    $ticket = Ticket::factory()->awaitingValidation()->create(['user_id' => $owner->id]);

    $this->actingAs($owner);

    $component = Livewire::test('tickets.ticket-validation-actions', ['ticket' => $ticket]);

    if ($rating !== null) {
        $component->set('rating', $rating);
    }

    $component->call('confirm')->assertHasErrors(['rating']);

    $ticket->refresh();

    expect($ticket->validation_status)->toBe(ValidationStatus::Pending);
    expect($ticket->resolution_rating)->toBeNull();
})->with([null, 0, 6]);

test('the author rejects the resolution with a reason, reopening the ticket', function () {
    $owner = User::factory()->create();
    $agent = User::factory()->admin()->create();
    $ticket = Ticket::factory()->awaitingValidation()->create([
        'user_id' => $owner->id,
        'assigned_to' => $agent->id,
    ]);

    $this->actingAs($owner);

    Livewire::test('tickets.ticket-validation-actions', ['ticket' => $ticket])
        ->set('rejectionReason', 'El servicio volvió a caerse apenas cerraron el ticket.')
        ->call('reject')
        ->assertHasNoErrors();

    $ticket->refresh();

    expect($ticket->validation_status)->toBe(ValidationStatus::Rejected);
    expect($ticket->status)->toBe('in_progress');
    expect($ticket->assigned_to)->toBe($agent->id);
    expect($ticket->messages()->where('body', 'El servicio volvió a caerse apenas cerraron el ticket.')->exists())->toBeTrue();
});

test('rejecting without a reason fails validation and changes neither validation nor status', function () {
    $owner = User::factory()->create();
    $ticket = Ticket::factory()->awaitingValidation()->create(['user_id' => $owner->id]);

    $this->actingAs($owner);

    Livewire::test('tickets.ticket-validation-actions', ['ticket' => $ticket])
        ->set('rejectionReason', '')
        ->call('reject')
        ->assertHasErrors(['rejectionReason']);

    $ticket->refresh();

    expect($ticket->validation_status)->toBe(ValidationStatus::Pending);
    expect($ticket->status)->toBe('resolved');
});

test('rejecting the resolution does not record a rating', function () {
    $owner = User::factory()->create();
    $ticket = Ticket::factory()->awaitingValidation()->create(['user_id' => $owner->id]);

    $this->actingAs($owner);

    Livewire::test('tickets.ticket-validation-actions', ['ticket' => $ticket])
        ->set('rating', 5)
        ->set('rejectionReason', 'Sigue sin funcionar como pedimos.')
        ->call('reject')
        ->assertHasNoErrors();

    expect($ticket->refresh()->resolution_rating)->toBeNull();
});

test('an admin cannot validate a ticket they filed on behalf of a client', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->awaitingValidation()->create([
        'user_id' => $owner->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-validation-actions', ['ticket' => $ticket])
        ->set('rating', 5)
        ->call('confirm')
        ->assertForbidden();

    Livewire::test('tickets.ticket-validation-actions', ['ticket' => $ticket])
        ->set('rejectionReason', 'Motivo cualquiera.')
        ->call('reject')
        ->assertForbidden();

    expect($ticket->refresh()->validation_status)->toBe(ValidationStatus::Pending);
});

test('the author cannot validate a ticket that is not awaiting validation', function (string $validationStatus) {
    $owner = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'user_id' => $owner->id,
        'status' => 'resolved',
        'validation_status' => $validationStatus,
    ]);

    $this->actingAs($owner);

    expect(Gate::forUser($owner)->allows('validateResolution', $ticket))->toBeFalse();

    Livewire::test('tickets.ticket-validation-actions', ['ticket' => $ticket])
        ->set('rating', 5)
        ->call('confirm')
        ->assertHasNoErrors();

    Livewire::test('tickets.ticket-validation-actions', ['ticket' => $ticket])
        ->set('rejectionReason', 'Motivo cualquiera.')
        ->call('reject')
        ->assertHasNoErrors();

    expect($ticket->refresh()->validation_status->value)->toBe($validationStatus);
})->with([
    ValidationStatus::NotRequested->value,
    ValidationStatus::Confirmed->value,
    ValidationStatus::Rejected->value,
]);

test('the validation actions do not render for anyone other than the author awaiting validation', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->awaitingValidation()->create(['user_id' => $owner->id]);

    $this->actingAs($admin)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertDontSeeLivewire('tickets.ticket-validation-actions');

    $this->actingAs($owner)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSeeLivewire('tickets.ticket-validation-actions');
});

test('resolving a rejected ticket again asks for a new validation', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create([
        'status' => 'in_progress',
        'validation_status' => ValidationStatus::Rejected,
    ]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'resolved');

    expect($ticket->refresh()->validation_status)->toBe(ValidationStatus::Pending);
});

test('a new validation request discards the rating of the previous cycle', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->validated()->create(['resolution_rating' => 5]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'in_progress');

    expect($ticket->refresh()->resolution_rating)->toBe(5);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket->refresh()])
        ->call('updateStatus', 'resolved');

    $ticket->refresh();

    expect($ticket->validation_status)->toBe(ValidationStatus::Pending);
    expect($ticket->resolution_rating)->toBeNull();
    expect($ticket->validated_at)->toBeNull();
});

test('every validation decision is recorded in the ticket history', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['user_id' => $owner->id, 'status' => 'in_progress']);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'resolved');

    $requested = TicketHistory::query()
        ->where('ticket_id', $ticket->id)
        ->where('field', 'validation_status')
        ->latest('id')
        ->first();

    expect($requested->from_value)->toBe(ValidationStatus::NotRequested->value);
    expect($requested->to_value)->toBe(ValidationStatus::Pending->value);
    expect($requested->user_id)->toBe($admin->id);

    $this->actingAs($owner);

    Livewire::test('tickets.ticket-validation-actions', ['ticket' => $ticket->refresh()])
        ->set('rating', 3)
        ->call('confirm')
        ->assertHasNoErrors();

    $confirmed = TicketHistory::query()
        ->where('ticket_id', $ticket->id)
        ->where('field', 'validation_status')
        ->latest('id')
        ->first();

    expect($confirmed->from_value)->toBe(ValidationStatus::Pending->value);
    expect($confirmed->to_value)->toBe(ValidationStatus::Confirmed->value);
    expect($confirmed->user_id)->toBe($owner->id);
});

test('an admin sees the rating the client gave on the ticket detail page', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->validated()->create(['resolution_rating' => 4]);

    $this->actingAs($admin)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSee(__('Calificación'))
        ->assertSee(__('Validado'));
});

test('the admin dashboard reports pending validations and the average rating', function () {
    $admin = User::factory()->admin()->create();

    Ticket::factory()->awaitingValidation()->count(2)->create();
    Ticket::factory()->validated()->create(['resolution_rating' => 5]);
    Ticket::factory()->validated()->create(['resolution_rating' => 2]);

    $this->actingAs($admin);

    Livewire::test('dashboard')
        ->assertViewHas('pendingValidationCount', 2)
        ->assertViewHas('avgRating', 3.5);
});

test('a client dashboard counts only their own tickets awaiting validation', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    Ticket::factory()->awaitingValidation()->count(2)->create(['user_id' => $owner->id]);
    Ticket::factory()->awaitingValidation()->create(['user_id' => $other->id]);

    $this->actingAs($owner);

    Livewire::test('dashboard')
        ->assertViewHas('pendingValidationCount', 2)
        ->assertSee(__('Entrá a cada ticket para confirmar si el pedido quedó resuelto y calificar la solución.'));
});

test('a ticket awaiting validation shows up among the client\'s finished tickets', function () {
    $owner = User::factory()->create();
    $ticket = Ticket::factory()->awaitingValidation()->create(['user_id' => $owner->id]);

    $this->actingAs($owner);

    Livewire::test('tickets.ticket-list', ['statusFilter' => 'finished'])
        ->assertViewHas('tickets', fn ($tickets) => in_array($ticket->id, $tickets->pluck('id')->all(), true))
        ->assertSee(__('Pendiente de validación'));
});

test('the pending validation tab lists only the tickets awaiting the author\'s answer', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $awaiting = Ticket::factory()->awaitingValidation()->create(['user_id' => $owner->id]);
    $alreadyValidated = Ticket::factory()->validated()->create(['user_id' => $owner->id]);
    $stillOpen = Ticket::factory()->create(['user_id' => $owner->id, 'status' => 'in_progress']);
    $someoneElses = Ticket::factory()->awaitingValidation()->create(['user_id' => $other->id]);

    $this->actingAs($owner);

    Livewire::test('tickets.ticket-list', ['statusFilter' => 'pending_validation'])
        ->assertViewHas('tickets', function ($tickets) use ($awaiting, $alreadyValidated, $stillOpen, $someoneElses) {
            $ids = $tickets->pluck('id')->all();

            return $ids === [$awaiting->id]
                && ! in_array($alreadyValidated->id, $ids, true)
                && ! in_array($stillOpen->id, $ids, true)
                && ! in_array($someoneElses->id, $ids, true);
        });
});

test('the pending validation tab leaves out tickets merely assigned to an admin', function () {
    $admin = User::factory()->admin()->create();
    $assigned = Ticket::factory()->awaitingValidation()->create(['assigned_to' => $admin->id]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-list', ['statusFilter' => 'pending_validation'])
        ->assertViewHas('tickets', fn ($tickets) => ! in_array($assigned->id, $tickets->pluck('id')->all(), true));
});

test('the pending validation page shows the tickets awaiting the author\'s answer', function () {
    $owner = User::factory()->create();
    $awaiting = Ticket::factory()->awaitingValidation()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->get(route('ticket.pending-validation'))
        ->assertOk()
        ->assertSee($awaiting->title)
        ->assertSee(__('Estos tickets esperan tu confirmación'));
});

test('every ticket tab offers a pending validation filter carrying its count', function (string $route) {
    $owner = User::factory()->create();

    Ticket::factory()->awaitingValidation()->count(3)->create(['user_id' => $owner->id]);
    Ticket::factory()->awaitingValidation()->create();

    $this->actingAs($owner)
        ->get(route($route))
        ->assertOk()
        ->assertSee(route('ticket.pending-validation'))
        ->assertSee(__('Por validar (:count)', ['count' => 3]));
})->with(['ticket.index', 'ticket.finished', 'ticket.drafts', 'ticket.pending-validation']);

test('the pending validation filter drops its count once nothing is awaiting an answer', function () {
    $owner = User::factory()->create();

    Ticket::factory()->validated()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->get(route('ticket.index'))
        ->assertOk()
        ->assertSee(__('Por validar'))
        ->assertDontSee(__('Por validar (:count)', ['count' => 1]));
});

test('the client dashboard links to the pending validation tab', function () {
    $owner = User::factory()->create();

    Ticket::factory()->awaitingValidation()->create(['user_id' => $owner->id]);

    $this->actingAs($owner);

    Livewire::test('dashboard')
        ->assertSee(route('ticket.pending-validation'))
        ->assertSee(__('Ver pendientes'));
});
