<?php

use App\Enums\TriageStatus;
use App\Enums\ValidationStatus;
use App\Models\Area;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

/**
 * Members of an area can read the submitted tickets filed for it, comment on
 * them and validate their resolution. Editing stays with the author.
 */
test('a member of the area can open a teammate\'s ticket', function () {
    $area = Area::factory()->create();
    $author = User::factory()->withAreas($area)->create();
    $teammate = User::factory()->withAreas($area)->create();
    $ticket = Ticket::factory()->for($author)->create(['area_id' => $area->id, 'status' => 'open']);

    $this->actingAs($teammate)->get(route('ticket.show', $ticket))->assertOk();
});

test('a client cannot open a ticket from an area they do not belong to', function () {
    $comercial = Area::factory()->create();
    $tecnica = Area::factory()->create();
    $ticket = Ticket::factory()->create(['area_id' => $tecnica->id, 'status' => 'open']);

    $this->actingAs(User::factory()->withAreas($comercial)->create())
        ->get(route('ticket.show', $ticket))
        ->assertForbidden();
});

test('a client cannot open someone else\'s ticket without an area', function () {
    $area = Area::factory()->create();
    $ticket = Ticket::factory()->create(['area_id' => null, 'status' => 'open']);

    $this->actingAs(User::factory()->withAreas($area)->create())
        ->get(route('ticket.show', $ticket))
        ->assertForbidden();
});

test('a teammate cannot open someone else\'s draft', function () {
    $area = Area::factory()->create();
    $draft = Ticket::factory()->draft()->create(['area_id' => $area->id]);

    $this->actingAs(User::factory()->withAreas($area)->create())
        ->get(route('ticket.show', $draft))
        ->assertForbidden();
});

test('leaving an area hides its teammates\' tickets but not your own', function () {
    $area = Area::factory()->create();
    $user = User::factory()->withAreas($area)->create();
    $own = Ticket::factory()->for($user)->create(['area_id' => $area->id, 'status' => 'open']);
    $teammates = Ticket::factory()->create(['area_id' => $area->id, 'status' => 'open']);

    $user->areas()->detach($area);

    $this->actingAs($user);
    $this->get(route('ticket.show', $teammates))->assertForbidden();
    $this->get(route('ticket.show', $own))->assertOk();
});

test('a teammate cannot edit or revise someone else\'s ticket', function () {
    $area = Area::factory()->create();
    $teammate = User::factory()->withAreas($area)->create();
    $pendingTriage = Ticket::factory()->create(['area_id' => $area->id, 'status' => 'open', 'triage_status' => TriageStatus::Pending]);
    $rejected = Ticket::factory()->create(['area_id' => $area->id, 'status' => 'open', 'triage_status' => TriageStatus::Rejected]);

    expect($teammate->can('view', $pendingTriage))->toBeTrue()
        ->and($teammate->can('edit', $pendingTriage))->toBeFalse()
        ->and($teammate->can('reviseTriage', $rejected))->toBeFalse();
});

test('a teammate can confirm the resolution of an area ticket', function () {
    $area = Area::factory()->create();
    $teammate = User::factory()->withAreas($area)->create();
    $ticket = Ticket::factory()->awaitingValidation()->create(['area_id' => $area->id]);

    $this->actingAs($teammate)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSeeLivewire('tickets.ticket-validation-actions');

    Livewire::test('tickets.ticket-validation-actions', ['ticket' => $ticket])
        ->set('rating', 4)
        ->call('confirm')
        ->assertHasNoErrors();

    $ticket->refresh();

    expect($ticket->validation_status)->toBe(ValidationStatus::Confirmed)
        ->and($ticket->resolution_rating)->toBe(4)
        ->and($ticket->history()->where('field', 'validation_status')->latest('id')->first()->user_id)->toBe($teammate->id);
});

test('a teammate can reject the resolution of an area ticket', function () {
    $area = Area::factory()->create();
    $teammate = User::factory()->withAreas($area)->create();
    $ticket = Ticket::factory()->awaitingValidation()->create(['area_id' => $area->id]);

    $this->actingAs($teammate);

    Livewire::test('tickets.ticket-validation-actions', ['ticket' => $ticket])
        ->set('rejectionReason', 'En nuestra sucursal sigue fallando.')
        ->call('reject')
        ->assertHasNoErrors();

    expect($ticket->refresh()->validation_status)->toBe(ValidationStatus::Rejected)
        ->and($ticket->status)->toBe('in_progress');
});

test('nobody outside the area validates, and admins never do even inside it', function () {
    $area = Area::factory()->create();
    $ticket = Ticket::factory()->awaitingValidation()->create(['area_id' => $area->id]);
    $outsider = User::factory()->withAreas(Area::factory()->create())->create();
    $adminInArea = User::factory()->admin()->withAreas($area)->create();

    expect($outsider->can('validateResolution', $ticket))->toBeFalse()
        ->and($adminInArea->can('validateResolution', $ticket))->toBeFalse();

    $this->actingAs($adminInArea);

    Livewire::test('tickets.ticket-validation-actions', ['ticket' => $ticket])
        ->set('rating', 5)
        ->call('confirm')
        ->assertForbidden();
});

test('a teammate can comment in the chat of an area ticket', function () {
    $area = Area::factory()->create();
    $teammate = User::factory()->withAreas($area)->create();
    $ticket = Ticket::factory()->create(['area_id' => $area->id, 'status' => 'in_progress']);

    $this->actingAs($teammate);

    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket])
        ->assertSeeHtml('wire:submit.prevent="send"')
        ->set('body', '<p>Desde nuestra sucursal vemos lo mismo.</p>')
        ->call('send')
        ->assertHasNoErrors();

    expect($ticket->messages()->where('user_id', $teammate->id)->count())->toBe(1)
        ->and($ticket->fresh()->status)->toBe('in_progress');
});

test('a teammate answering a ticket awaiting response puts it back in progress', function () {
    $area = Area::factory()->create();
    $teammate = User::factory()->withAreas($area)->create();
    $ticket = Ticket::factory()->awaitingResponse()->create(['area_id' => $area->id]);

    $this->actingAs($teammate);

    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket])
        ->assertSee(__('El equipo está esperando tu respuesta para continuar.'))
        ->set('body', '<p>Les paso el dato que faltaba.</p>')
        ->call('send')
        ->assertHasNoErrors();

    expect($ticket->fresh()->status)->toBe('in_progress');
});

test('an admin who belongs to the area does not resume a ticket awaiting response by replying', function () {
    $area = Area::factory()->create();
    $admin = User::factory()->admin()->withAreas($area)->create();
    $ticket = Ticket::factory()->awaitingResponse()->create(['area_id' => $area->id]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket])
        ->set('body', '<p>¿Pudieron revisarlo?</p>')
        ->call('send')
        ->assertHasNoErrors();

    expect($ticket->fresh()->status)->toBe('awaiting_response');
});

test('someone outside the area cannot post in the chat', function () {
    $ticket = Ticket::factory()->create(['area_id' => Area::factory()->create()->id, 'status' => 'open']);
    $outsider = User::factory()->withAreas(Area::factory()->create())->create();

    expect($outsider->can('reply', $ticket))->toBeFalse();

    $this->actingAs($outsider);

    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket])
        ->set('body', '<p>Hola</p>')
        ->call('send')
        ->assertForbidden();

    expect($ticket->messages()->count())->toBe(0);
});

test('the author and admins can still post in the chat', function () {
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($ticket->user);
    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket])
        ->set('body', '<p>Sumo un dato</p>')
        ->call('send')
        ->assertHasNoErrors();

    $this->actingAs(User::factory()->admin()->create());
    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket])
        ->set('body', '<p>Lo revisamos</p>')
        ->call('send')
        ->assertHasNoErrors();

    expect($ticket->messages()->count())->toBe(2);
});
