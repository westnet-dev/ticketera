<?php

use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\TicketSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

test('a client can save a draft with only a title', function () {
    $client = User::factory()->create();

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Falta terminar esto')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $draft = Ticket::first();

    expect($draft->status)->toBe('draft');
    expect($draft->title)->toBe('Falta terminar esto');
    expect($draft->description)->toBeNull();
});

test('a draft does not appear in admin triage or the admin ticket list', function () {
    $client = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $draft = Ticket::factory()->for($client)->draft()->create();

    $this->actingAs($admin)->get(route('admin.triage'))->assertDontSee($draft->title);
    $this->actingAs($admin)->get(route('admin.tickets'))->assertDontSee($draft->title);
});

test('the owning client can edit their own draft', function () {
    $client = User::factory()->create();
    $draft = Ticket::factory()->for($client)->draft()->create(['title' => 'Titulo viejo']);

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket', ['draft' => $draft])
        ->assertSet('title', 'Titulo viejo')
        ->set('title', 'Titulo nuevo')
        ->call('saveDraft')
        ->assertHasNoErrors();

    expect($draft->refresh()->title)->toBe('Titulo nuevo');
    expect($draft->status)->toBe('draft');
});

test('the owning client can delete their own draft', function () {
    $client = User::factory()->create();
    $draft = Ticket::factory()->for($client)->draft()->create();

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket', ['draft' => $draft])
        ->call('deleteDraft')
        ->assertRedirect(route('ticket.drafts'));

    expect(Ticket::find($draft->id))->toBeNull();
});

test('a different client cannot view, edit, or delete someone else\'s draft', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $draft = Ticket::factory()->for($owner)->draft()->create();

    $this->actingAs($other)->get(route('ticket.show', $draft))->assertForbidden();

    $this->actingAs($other);

    Livewire::test('tickets.create-ticket', ['draft' => $draft])
        ->set('title', 'Intento ajeno')
        ->call('saveDraft')
        ->assertForbidden();

    Livewire::test('tickets.create-ticket', ['draft' => $draft])
        ->call('deleteDraft')
        ->assertForbidden();
});

test('an admin cannot view someone else\'s draft', function () {
    $client = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $draft = Ticket::factory()->for($client)->draft()->create();

    $this->actingAs($admin)->get(route('ticket.show', $draft))->assertForbidden();
});

test('submitting a complete draft moves it to open with pending triage for a client', function () {
    $client = User::factory()->create();
    $draft = Ticket::factory()->for($client)->draft()->create();

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket', ['draft' => $draft])
        ->set('title', 'El servicio está caído')
        ->set('description', 'No hay conexión en toda la oficina desde hace una hora.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('submit')
        ->assertHasNoErrors();

    $draft->refresh();

    expect($draft->status)->toBe('open');
    expect($draft->triage_status)->toBe(TriageStatus::Pending);
});

test('submitting a complete draft moves it to open with approved triage for an admin', function () {
    $admin = User::factory()->admin()->create();
    $draft = Ticket::factory()->for($admin)->draft()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.create-ticket', ['draft' => $draft])
        ->set('title', 'El servicio está caído')
        ->set('description', 'No hay conexión en toda la oficina desde hace una hora.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('submit')
        ->assertHasNoErrors();

    expect($draft->refresh()->status)->toBe('open');
    expect($draft->triage_status)->toBe(TriageStatus::Approved);
});

test('submitting an incomplete draft fails validation and stays a draft', function () {
    $client = User::factory()->create();
    $draft = Ticket::factory()->for($client)->draft()->create(['title' => 'Solo tengo el titulo']);

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket', ['draft' => $draft])
        ->call('submit')
        ->assertHasErrors(['description', 'images']);

    expect($draft->refresh()->status)->toBe('draft');
});

test('a client\'s draft tickets do not count toward the open-ticket cap', function () {
    TicketSetting::current()->update(['max_open_tickets_per_user' => 1]);

    $client = User::factory()->create();
    Ticket::factory()->for($client)->draft()->count(3)->create();
    Ticket::factory()->for($client)->create(['status' => 'open']);

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Otro ticket mas')
        ->set('description', 'Descripcion suficientemente larga.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertHasErrors(['title']);
});
