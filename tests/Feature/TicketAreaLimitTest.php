<?php

use App\Models\Area;
use App\Models\Ticket;
use App\Models\TicketSetting;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The ticket cap is an area-wide budget: it stays finite no matter how many
 * people the area has. The fallback for users with no area lives in
 * `TicketCreationLimitTest`.
 */
function fillAreaToCap(Area $area, string $status = 'open'): User
{
    $teammate = User::factory()->for($area)->create();

    Ticket::factory()
        ->count(TicketSetting::current()->max_open_tickets_per_area)
        ->create(['user_id' => $teammate->id, 'status' => $status]);

    return $teammate;
}

/**
 * @return Testable
 */
function attemptTicket(string $title, string $description)
{
    return Livewire::test('tickets.create-ticket')
        ->set('title', $title)
        ->set('description', $description)
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->call('save');
}

test('a client is blocked when their area reached the cap through a teammate', function () {
    $area = Area::factory()->create();
    fillAreaToCap($area);

    $client = User::factory()->for($area)->create();

    $this->actingAs($client);

    attemptTicket('No debería poder crear otro', 'Mi área ya llegó al tope aunque yo no tenga ninguno propio.')
        ->assertHasErrors(['title']);

    expect(Ticket::where('user_id', $client->id)->count())->toBe(0);
});

test('the blocking message names the area, not the user', function () {
    $area = Area::factory()->create();
    fillAreaToCap($area);

    $client = User::factory()->for($area)->create();

    $this->actingAs($client);

    $component = attemptTicket('No debería poder crear otro', 'Mi área ya llegó al tope aunque yo no tenga ninguno propio.');

    expect($component->errors()->first('title'))->toContain('Tu área alcanzó el máximo');
});

test('a client can create past their own count while their area is under the cap', function () {
    TicketSetting::current()->update(['max_open_tickets_per_area' => 10]);

    $area = Area::factory()->create();
    $client = User::factory()->for($area)->create();
    Ticket::factory()->count(8)->create(['user_id' => $client->id, 'status' => 'open']);

    $this->actingAs($client);

    attemptTicket('Puedo crear igual', 'El tope individual dejó de evaluarse: lo que manda es el total del área.')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->count())->toBe(9);
});

test('resolving a teammate ticket frees room for the whole area', function () {
    $area = Area::factory()->create();
    $teammate = fillAreaToCap($area);

    Ticket::where('user_id', $teammate->id)->first()->update(['status' => 'resolved']);

    $client = User::factory()->for($area)->create();

    $this->actingAs($client);

    attemptTicket('Ahora sí puedo crear', 'Se resolvió un ticket de un compañero y el área bajó del tope.')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->count())->toBe(1);
});

test('draft tickets of the area do not count toward the cap', function () {
    $area = Area::factory()->create();
    fillAreaToCap($area, 'draft');

    $client = User::factory()->for($area)->create();

    $this->actingAs($client);

    attemptTicket('Los borradores no cuentan', 'Mi área tiene muchos borradores pero ninguno enviado.')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->count())->toBe(1);
});

test('unclosed tickets of another area do not count', function () {
    fillAreaToCap(Area::factory()->create());

    $ownArea = Area::factory()->create();
    $client = User::factory()->for($ownArea)->create();

    $this->actingAs($client);

    attemptTicket('Otra área no me bloquea', 'El tope se cuenta por área, y la mía todavía no tiene nada.')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->count())->toBe(1);
});

test('a client at their own cap stays blocked after joining an empty area', function () {
    $client = User::factory()->create();
    Ticket::factory()
        ->count(TicketSetting::current()->max_open_tickets_per_area)
        ->create(['user_id' => $client->id, 'status' => 'open']);

    $this->actingAs($client);

    attemptTicket('Bloqueado sin área', 'Sin área me mido contra mis propios tickets y ya llegué al tope.')
        ->assertHasErrors(['title']);

    $client->update(['area_id' => Area::factory()->create()->id]);
    $this->actingAs($client->fresh());

    attemptTicket('Sigo bloqueado con área', 'Mis tickets abiertos entran al área conmigo, así que el área nace en el tope.')
        ->assertHasErrors(['title']);

    expect(Ticket::where('user_id', $client->id)->count())
        ->toBe(TicketSetting::current()->max_open_tickets_per_area);
});

test('joining an area changes the subject of the count to the whole team', function () {
    $area = Area::factory()->create();
    $teammate = User::factory()->for($area)->create();
    Ticket::factory()->count(4)->create(['user_id' => $teammate->id, 'status' => 'open']);

    $client = User::factory()->create();

    $this->actingAs($client);

    attemptTicket('Sin área puedo crear', 'No tengo tickets propios, así que nada me bloquea.')
        ->assertHasNoErrors();

    $client->update(['area_id' => $area->id]);
    $this->actingAs($client->fresh());

    attemptTicket('Con área ya no', 'Ahora los 4 de mi compañero más el mío llegan al tope del área.')
        ->assertHasErrors(['title']);

    expect(Ticket::where('user_id', $client->id)->count())->toBe(1);
});

test('an admin creates their own tickets even when their area is over the cap', function () {
    $area = Area::factory()->create();
    fillAreaToCap($area);

    $admin = User::factory()->admin()->for($area)->create();

    $this->actingAs($admin);

    attemptTicket('Pedido de admin sin tope', 'Los admins no están limitados por el tope del área.')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $admin->id)->count())->toBe(1);
});

test('an admin can file on behalf of a client whose area reached the cap', function () {
    $area = Area::factory()->create();
    fillAreaToCap($area);

    $client = User::factory()->for($area)->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.create-ticket')
        ->set('author_id', $client->id)
        ->set('title', 'Pedido transmitido de palabra')
        ->set('description', 'El área está en el tope pero el alta la ejecuta un admin, que está exento.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->count())->toBe(1);

    $this->actingAs($client);

    attemptTicket('Ahora sí estoy bloqueado', 'El ticket cargado a mi nombre cuenta para el tope de mi área.')
        ->assertHasErrors(['title']);
});

test('tickets of a soft-deleted user stop counting toward their area cap', function () {
    $area = Area::factory()->create();
    $teammate = fillAreaToCap($area);

    $client = User::factory()->for($area)->create();

    $this->actingAs($client);

    attemptTicket('Bloqueado por el compañero', 'El área está en el tope por los tickets de mi compañero.')
        ->assertHasErrors(['title']);

    $teammate->delete();

    attemptTicket('Desbloqueado tras la baja', 'Los tickets de alguien dado de baja dejan de consumir cupo del área.')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->count())->toBe(1);
});

test('lowering the cap below what an area already holds blocks creation without touching tickets', function () {
    $area = Area::factory()->create();
    $teammate = fillAreaToCap($area);

    $client = User::factory()->for($area)->create();
    $before = Ticket::where('user_id', $teammate->id)->pluck('status', 'id');

    TicketSetting::current()->update(['max_open_tickets_per_area' => 2]);

    $this->actingAs($client);

    attemptTicket('No debería poder crear', 'El tope bajó por debajo de lo que mi área ya acumula.')
        ->assertHasErrors(['title']);

    expect(Ticket::where('user_id', $teammate->id)->pluck('status', 'id')->all())->toBe($before->all());
    expect(Ticket::where('user_id', $client->id)->count())->toBe(0);
});

test('the configured cap applies to every area regardless of headcount', function () {
    TicketSetting::current()->update(['max_open_tickets_per_area' => 3]);

    $bigArea = Area::factory()->create();
    User::factory()->count(10)->for($bigArea)->create()
        ->each(fn (User $member) => Ticket::factory()->create(['user_id' => $member->id, 'status' => 'open']));

    $client = User::factory()->for($bigArea)->create();

    $this->actingAs($client);

    attemptTicket('Diez personas, un solo tope', 'Que el área tenga diez personas no le da diez veces el cupo.')
        ->assertHasErrors(['title']);

    expect(Ticket::where('user_id', $client->id)->count())->toBe(0);
});
