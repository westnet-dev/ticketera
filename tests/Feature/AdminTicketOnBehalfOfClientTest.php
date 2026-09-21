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

test('an admin can file a ticket on behalf of an existing client', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.create-ticket')
        ->set('author_id', $client->id)
        ->set('title', 'Necesito un reporte de consumo mensual')
        ->set('description', 'Pedido verbal: un reporte mensual de consumo por cliente.')
        ->set('priority', 8)
        ->set('urgency', 6)
        ->set('impact', 7)
        ->call('save')
        ->assertHasNoErrors();

    $ticket = Ticket::sole();

    expect($ticket->user_id)->toBe($client->id);
    expect($ticket->created_by)->toBe($admin->id);
    expect($ticket->triage_status)->toBe(TriageStatus::Approved);
    expect($ticket->wasCreatedOnBehalf())->toBeTrue();
});

test('the client sees the ticket filed on their behalf as their own', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->create();

    $ticket = Ticket::factory()->create([
        'user_id' => $client->id,
        'created_by' => $admin->id,
        'title' => 'Reporte de consumo mensual',
        'status' => 'open',
    ]);

    $this->actingAs($client)
        ->get(route('ticket.index'))
        ->assertOk()
        ->assertSee('Reporte de consumo mensual');

    $this->actingAs($client)
        ->get(route('ticket.show', $ticket))
        ->assertOk();
});

test('images are optional when an admin files on behalf of a client', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.create-ticket')
        ->set('author_id', $client->id)
        ->set('title', 'Pedido transmitido de palabra')
        ->set('description', 'No hay captura porque el pedido llegó en una reunión.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->count())->toBe(1);
});

test('a client still needs to attach an image to their own ticket', function () {
    $client = User::factory()->create();

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'No adjunto ninguna imagen')
        ->set('description', 'Este ticket no debería poder crearse sin evidencia.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->call('save')
        ->assertHasErrors(['images']);

    expect(Ticket::count())->toBe(0);
});

test('an admin filing their own ticket still needs to attach an image', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Pedido propio sin evidencia')
        ->set('description', 'Sin autor seleccionado la imagen sigue siendo obligatoria.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->call('save')
        ->assertHasErrors(['images']);

    expect(Ticket::count())->toBe(0);
});

test('a client cannot file a ticket authored by someone else', function () {
    $client = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('author_id', $other->id)
        ->set('title', 'Intento cargar un ticket ajeno')
        ->set('description', 'Un cliente no debería poder elegir el autor del ticket.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertForbidden();

    expect(Ticket::count())->toBe(0);
});

test('a client does not see the author selector', function () {
    $client = User::factory()->create();
    User::factory()->create(['name' => 'Cliente Que No Debe Figurar']);

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->assertDontSee('Cliente Que No Debe Figurar')
        ->assertDontSee(__('Autor'));
});

test('an admin sees only clients in the author selector', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Cliente Visible']);
    User::factory()->admin()->create(['name' => 'Otro Admin']);
    User::factory()->create(['name' => 'Cliente Dado De Baja'])->delete();

    $this->actingAs($admin);

    Livewire::test('tickets.create-ticket')
        ->assertSee('Cliente Visible')
        ->assertDontSee('Otro Admin')
        ->assertDontSee('Cliente Dado De Baja');
});

test('an admin cannot pick another admin as the author', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.create-ticket')
        ->set('author_id', $otherAdmin->id)
        ->set('title', 'Autor con rol equivocado')
        ->set('description', 'El autor tiene que ser un cliente, no otro admin.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->call('save')
        ->assertHasErrors(['author_id']);

    expect(Ticket::count())->toBe(0);
});

test('an admin cannot pick a non-existent user as the author', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.create-ticket')
        ->set('author_id', 99999)
        ->set('title', 'Autor inexistente')
        ->set('description', 'El autor indicado no corresponde a ningún usuario.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->call('save')
        ->assertHasErrors(['author_id']);

    expect(Ticket::count())->toBe(0);
});

test('an admin cannot pick a soft-deleted client as the author', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->create();
    $client->delete();

    $this->actingAs($admin);

    Livewire::test('tickets.create-ticket')
        ->set('author_id', $client->id)
        ->set('title', 'Autor dado de baja')
        ->set('description', 'El cliente elegido ya no está activo en el sistema.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->call('save')
        ->assertHasErrors(['author_id']);

    expect(Ticket::count())->toBe(0);
});

test('an admin can file for a client who already reached their ticket limit', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->create();

    Ticket::factory()
        ->count(TicketSetting::current()->max_open_tickets_per_user)
        ->create(['user_id' => $client->id, 'status' => 'open']);

    $this->actingAs($admin);

    Livewire::test('tickets.create-ticket')
        ->set('author_id', $client->id)
        ->set('title', 'Pedido cargado por encima del tope')
        ->set('description', 'El tope aplica a quien crea el ticket, no a su autor.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->count())
        ->toBe(TicketSetting::current()->max_open_tickets_per_user + 1);
});

test('a ticket filed on behalf counts toward the client limit afterwards', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->create();
    $max = TicketSetting::current()->max_open_tickets_per_user;

    Ticket::factory()->count($max - 1)->create(['user_id' => $client->id, 'status' => 'open']);

    $this->actingAs($admin);

    Livewire::test('tickets.create-ticket')
        ->set('author_id', $client->id)
        ->set('title', 'El pedido que completa el tope del cliente')
        ->set('description', 'Con este ticket el cliente queda en su máximo permitido.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->call('save')
        ->assertHasNoErrors();

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Ahora ya no puedo crear otro')
        ->set('description', 'El ticket que me cargaron cuenta para mi tope.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertHasErrors(['title']);

    expect(Ticket::where('user_id', $client->id)->count())->toBe($max);
});

test('a draft cannot be saved on behalf of another user', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.create-ticket')
        ->set('author_id', $client->id)
        ->set('title', 'Borrador a nombre de otro')
        ->call('saveDraft')
        ->assertHasErrors(['author_id']);

    expect(Ticket::count())->toBe(0);
});

test('the ticket detail shows who filed it on whose behalf', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin Soporte']);
    $client = User::factory()->create(['name' => 'Cliente Solicitante']);

    $ticket = Ticket::factory()->create([
        'user_id' => $client->id,
        'created_by' => $admin->id,
        'status' => 'open',
    ]);

    $this->actingAs($client)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSee(__('Creado por :author en nombre de :owner', [
            'author' => 'Admin Soporte',
            'owner' => 'Cliente Solicitante',
        ]));
});

test('the ticket detail shows no authorship line for a ticket without a recorded creator', function () {
    $client = User::factory()->create(['name' => 'Cliente Solicitante']);

    $ticket = Ticket::factory()->create([
        'user_id' => $client->id,
        'created_by' => null,
        'status' => 'open',
    ]);

    $this->actingAs($client)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertDontSee('en nombre de');
});

test('an admin filing their own ticket records themselves as author and creator', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Pedido propio del admin')
        ->set('description', 'Sin autor seleccionado el ticket sigue siendo del admin.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    $ticket = Ticket::sole();

    expect($ticket->user_id)->toBe($admin->id);
    expect($ticket->created_by)->toBe($admin->id);
    expect($ticket->triage_status)->toBe(TriageStatus::Approved);
    expect($ticket->wasCreatedOnBehalf())->toBeFalse();
});

test('a client filing their own ticket still goes through triage', function () {
    $client = User::factory()->create();

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Pedido propio del cliente')
        ->set('description', 'Este ticket sigue entrando a la cola de triage.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    $ticket = Ticket::sole();

    expect($ticket->user_id)->toBe($client->id);
    expect($ticket->created_by)->toBe($client->id);
    expect($ticket->triage_status)->toBe(TriageStatus::Pending);
});
