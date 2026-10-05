<?php

use App\Enums\Level;
use App\Models\Area;
use App\Models\Ticket;
use App\Models\TicketSetting;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The ticket cap is an area-wide budget: it stays finite no matter how many
 * people the area has, and it counts the tickets filed for the area, not the
 * tickets of its current members. The fallback for users with no area lives
 * in `TicketCreationLimitTest`.
 */
function fillAreaToCap(Area $area, string $status = 'open'): User
{
    $teammate = User::factory()->withAreas($area)->create();

    Ticket::factory()
        ->count(TicketSetting::current()->max_open_tickets_per_area)
        ->create(['user_id' => $teammate->id, 'area_id' => $area->id, 'status' => $status]);

    return $teammate;
}

/**
 * @return Testable
 */
function attemptTicket(string $title, string $description, ?Area $area = null)
{
    return Livewire::test('tickets.create-ticket')
        ->set('area_id', $area?->id ?? '')
        ->set('title', $title)
        ->set('description', $description)
        ->set('importance', Level::Medium->value)
        ->set('urgency', Level::Medium->value)
        ->set('impact', Level::Medium->value)
        ->call('save');
}

test('a client is blocked when their area reached the cap through a teammate', function () {
    $area = Area::factory()->create();
    fillAreaToCap($area);

    $client = User::factory()->withAreas($area)->create();

    $this->actingAs($client);

    attemptTicket('No debería poder crear otro', 'Mi área ya llegó al tope aunque yo no tenga ninguno propio.')
        ->assertHasErrors(['title']);

    expect(Ticket::where('user_id', $client->id)->count())->toBe(0);
});

test('the blocking message names the area, not the user', function () {
    $area = Area::factory()->create(['title' => 'Comercial']);
    fillAreaToCap($area);

    $client = User::factory()->withAreas($area)->create();

    $this->actingAs($client);

    $component = attemptTicket('No debería poder crear otro', 'Mi área ya llegó al tope aunque yo no tenga ninguno propio.');

    expect($component->errors()->first('title'))->toContain('El área Comercial alcanzó el máximo');
});

test('a client with several areas can file for the one that still has room', function () {
    $fullArea = Area::factory()->create();
    fillAreaToCap($fullArea);
    $openArea = Area::factory()->create();

    $client = User::factory()->withAreas($fullArea, $openArea)->create();

    $this->actingAs($client);

    attemptTicket('Va al área con cupo', 'Una de mis áreas está en el tope pero la otra no.', $openArea)
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->sole()->area_id)->toBe($openArea->id);
});

test('a client with several areas is blocked when filing for the full one', function () {
    $fullArea = Area::factory()->create(['title' => 'Comercial']);
    fillAreaToCap($fullArea);
    $openArea = Area::factory()->create();

    $client = User::factory()->withAreas($fullArea, $openArea)->create();

    $this->actingAs($client);

    $component = attemptTicket('Va al área llena', 'Elegí el área que ya está en el tope.', $fullArea)
        ->assertHasErrors(['title']);

    expect($component->errors()->first('title'))->toContain('El área Comercial alcanzó el máximo');
    expect(Ticket::where('user_id', $client->id)->count())->toBe(0);
});

test('a client can create past their own count while the area is under the cap', function () {
    TicketSetting::current()->update(['max_open_tickets_per_area' => 10]);

    $area = Area::factory()->create();
    $otherArea = Area::factory()->create();
    $client = User::factory()->withAreas($area, $otherArea)->create();
    Ticket::factory()->count(4)->create(['user_id' => $client->id, 'area_id' => $area->id, 'status' => 'open']);
    Ticket::factory()->count(8)->create(['user_id' => $client->id, 'area_id' => $otherArea->id, 'status' => 'open']);

    $this->actingAs($client);

    attemptTicket('Puedo crear igual', 'El tope individual no se evalúa: lo que manda es el total del área elegida.', $area)
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->count())->toBe(13);
});

test('resolving a teammate ticket frees room for the whole area', function () {
    $area = Area::factory()->create();
    $teammate = fillAreaToCap($area);

    Ticket::where('user_id', $teammate->id)->first()->update(['status' => 'resolved']);

    $client = User::factory()->withAreas($area)->create();

    $this->actingAs($client);

    attemptTicket('Ahora sí puedo crear', 'Se resolvió un ticket de un compañero y el área bajó del tope.')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->count())->toBe(1);
});

test('draft tickets of the area do not count toward the cap', function () {
    $area = Area::factory()->create();
    fillAreaToCap($area, 'draft');

    $client = User::factory()->withAreas($area)->create();

    $this->actingAs($client);

    attemptTicket('Los borradores no cuentan', 'Mi área tiene muchos borradores pero ninguno enviado.')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->count())->toBe(1);
});

test('unclosed tickets of another area do not count', function () {
    fillAreaToCap(Area::factory()->create());

    $ownArea = Area::factory()->create();
    $client = User::factory()->withAreas($ownArea)->create();

    $this->actingAs($client);

    attemptTicket('Otra área no me bloquea', 'El tope se cuenta por área, y la mía todavía no tiene nada.')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->count())->toBe(1);
});

test('tickets filed for an area keep counting after their author leaves it', function () {
    $area = Area::factory()->create();
    $teammate = fillAreaToCap($area);

    $teammate->areas()->detach();

    $client = User::factory()->withAreas($area)->create();

    $this->actingAs($client);

    attemptTicket('Sigo bloqueado', 'Los tickets del área siguen pendientes aunque su autor ya no esté en ella.')
        ->assertHasErrors(['title']);

    expect(Ticket::where('user_id', $client->id)->count())->toBe(0);
});

test('tickets of a soft-deleted user keep counting toward the area they were filed for', function () {
    $area = Area::factory()->create();
    $teammate = fillAreaToCap($area);

    $teammate->delete();

    $client = User::factory()->withAreas($area)->create();

    $this->actingAs($client);

    attemptTicket('Sigo bloqueado tras la baja', 'El trabajo pendiente sigue siendo del área aunque la persona se haya ido.')
        ->assertHasErrors(['title']);

    expect(Ticket::where('user_id', $client->id)->count())->toBe(0);
});

test('a client at their own cap can file once they join an area with room', function () {
    $client = User::factory()->create();
    Ticket::factory()
        ->count(TicketSetting::current()->max_open_tickets_per_area)
        ->create(['user_id' => $client->id, 'status' => 'open']);

    $this->actingAs($client);

    attemptTicket('Bloqueado sin área', 'Sin área me mido contra mis propios tickets y ya llegué al tope.')
        ->assertHasErrors(['title']);

    $area = Area::factory()->create();
    $client->areas()->attach($area);

    attemptTicket('Con área puedo crear', 'Mis tickets viejos no tienen área, así que el área nueva tiene cupo.')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->latest('id')->first()->area_id)->toBe($area->id);
});

test('an admin creates their own tickets even when their area is over the cap', function () {
    $area = Area::factory()->create();
    fillAreaToCap($area);

    $admin = User::factory()->admin()->withAreas($area)->create();

    $this->actingAs($admin);

    attemptTicket('Pedido de admin sin tope', 'Los admins no están limitados por el tope del área.')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $admin->id)->count())->toBe(1);
});

test('an admin can file on behalf of a client whose area reached the cap', function () {
    $area = Area::factory()->create();
    fillAreaToCap($area);

    $client = User::factory()->withAreas($area)->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('tickets.create-ticket')
        ->set('author_id', $client->id)
        ->set('title', 'Pedido transmitido de palabra')
        ->set('description', 'El área está en el tope pero el alta la ejecuta un admin, que está exento.')
        ->set('importance', Level::Medium->value)
        ->set('urgency', Level::Medium->value)
        ->set('impact', Level::Medium->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::where('user_id', $client->id)->sole()->area_id)->toBe($area->id);

    $this->actingAs($client);

    attemptTicket('Ahora sí estoy bloqueado', 'El ticket cargado a mi nombre cuenta para el tope de mi área.')
        ->assertHasErrors(['title']);
});

test('lowering the cap below what an area already holds blocks creation without touching tickets', function () {
    $area = Area::factory()->create();
    $teammate = fillAreaToCap($area);

    $client = User::factory()->withAreas($area)->create();
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
    User::factory()->count(10)->withAreas($bigArea)->create()
        ->each(fn (User $member) => Ticket::factory()->create(['user_id' => $member->id, 'area_id' => $bigArea->id, 'status' => 'open']));

    $client = User::factory()->withAreas($bigArea)->create();

    $this->actingAs($client);

    attemptTicket('Diez personas, un solo tope', 'Que el área tenga diez personas no le da diez veces el cupo.')
        ->assertHasErrors(['title']);

    expect(Ticket::where('user_id', $client->id)->count())->toBe(0);
});
