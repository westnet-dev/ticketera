<?php

use App\Models\Ticket;
use App\Models\TicketSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

/**
 * The cap is an area-wide budget. A user with no area has no budget to share and
 * falls back to counting their own tickets, which is what this file covers —
 * `User::factory()` leaves `area_id` null. The per-area rule lives in
 * `TicketAreaLimitTest`.
 */
test('a client without an area under the limit can create a new ticket', function () {
    $client = User::factory()->create();
    Ticket::factory()->count(4)->create(['user_id' => $client->id, 'status' => 'open']);

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Necesito ayuda con mi conexión')
        ->set('description', 'La conexión se corta varias veces al día.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->count())->toBe(5);
});

test('a client without an area who already reached the limit cannot create a new ticket', function () {
    $client = User::factory()->create();
    Ticket::factory()->count(5)->create(['user_id' => $client->id, 'status' => 'open']);

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Otro pedido más')
        ->set('description', 'Este pedido no debería poder crearse.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertHasErrors(['title']);

    expect(Ticket::where('user_id', $client->id)->count())->toBe(5);
});

test('the blocking message for a client without an area names their own cap', function () {
    $client = User::factory()->create();
    Ticket::factory()->count(5)->create(['user_id' => $client->id, 'status' => 'open']);

    $this->actingAs($client);

    $component = Livewire::test('tickets.create-ticket')
        ->set('title', 'Otro pedido más')
        ->set('description', 'Este pedido no debería poder crearse.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->call('save');

    expect($component->errors()->first('title'))
        ->toContain('Alcanzaste el máximo')
        ->not->toContain('Tu área');
});

test('resolving one of their own tickets frees up room for a client without an area', function () {
    $client = User::factory()->create();
    $tickets = Ticket::factory()->count(5)->create(['user_id' => $client->id, 'status' => 'open']);
    $tickets->first()->update(['status' => 'resolved']);

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Ahora sí puedo crear otro')
        ->set('description', 'Uno de mis tickets anteriores ya se resolvió.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->count())->toBe(6);
});

test('resolved and cancelled tickets do not count toward the limit', function () {
    $client = User::factory()->create();
    Ticket::factory()->count(6)->create(['user_id' => $client->id, 'status' => 'resolved']);
    Ticket::factory()->count(4)->create(['user_id' => $client->id, 'status' => 'cancelled']);
    Ticket::factory()->count(2)->create(['user_id' => $client->id, 'status' => 'open']);

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Puedo crear porque los finalizados no cuentan')
        ->set('description', 'Tengo muchos tickets finalizados pero solo 2 abiertos.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertHasNoErrors();
});

test('paused tickets still count toward the limit', function () {
    $client = User::factory()->create();
    Ticket::factory()->count(5)->create(['user_id' => $client->id, 'status' => 'paused']);

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'No debería poder crear otro')
        ->set('description', 'Mis tickets pausados siguen contando para el límite.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertHasErrors(['title']);
});

test('the unclosed tickets of another client without an area do not count', function () {
    $client = User::factory()->create();
    $stranger = User::factory()->create();
    Ticket::factory()->count(8)->create(['user_id' => $stranger->id, 'status' => 'open']);

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Los tickets ajenos no me bloquean')
        ->set('description', 'Sin área, cada cliente se mide solamente contra sus propios tickets.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->count())->toBe(1);
});

test('an admin can create tickets past the limit configured for clients', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->count(5)->create(['user_id' => $admin->id, 'status' => 'open']);

    $this->actingAs($admin);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Pedido de admin sin límite')
        ->set('description', 'Los admins no están limitados por esta configuración.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $admin->id)->count())->toBe(6);
});

test('an admin can view and update the ticket settings page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('tickets.settings'))
        ->assertOk();

    Livewire::test('pages::settings.tickets')
        ->set('max_open_tickets_per_area', 3)
        ->call('save')
        ->assertHasNoErrors();

    expect(TicketSetting::current()->max_open_tickets_per_area)->toBe(3);
});

test('a client cannot access the ticket settings page', function () {
    $client = User::factory()->create();

    $this->actingAs($client)
        ->get(route('tickets.settings'))
        ->assertForbidden();
});

test('lowering the limit does not affect existing tickets, only blocks new creation', function () {
    $client = User::factory()->create();
    Ticket::factory()->count(5)->create(['user_id' => $client->id, 'status' => 'open']);

    TicketSetting::current()->update(['max_open_tickets_per_area' => 2]);

    expect(Ticket::where('user_id', $client->id)->count())->toBe(5);

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'No debería poder crear otro')
        ->set('description', 'El límite bajó por debajo de lo que ya tengo.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('save')
        ->assertHasErrors(['title']);

    expect(Ticket::where('user_id', $client->id)->count())->toBe(5);
});
