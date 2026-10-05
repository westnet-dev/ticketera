<?php

use App\Enums\Level;
use App\Models\Area;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Fill the ticket form with valid content, leaving the author and area to the caller.
 *
 * @return Testable
 */
function ticketForm(array $params = [])
{
    return Livewire::test('tickets.create-ticket', $params)
        ->set('title', 'Pedido para un área')
        ->set('description', 'Necesito que este pedido quede imputado al área correcta.')
        ->set('importance', Level::Medium->value)
        ->set('urgency', Level::Medium->value)
        ->set('impact', Level::Medium->value);
}

test('a client without areas files a ticket without an area and sees no picker', function () {
    $this->actingAs(User::factory()->create());

    ticketForm()
        ->assertDontSee(__('Elegí para qué área es este ticket.'))
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::sole()->area_id)->toBeNull();
});

test('a client with a single area gets it assigned without a picker', function () {
    $area = Area::factory()->create();
    $this->actingAs(User::factory()->withAreas($area)->create());

    ticketForm()
        ->assertDontSee(__('Elegí para qué área es este ticket.'))
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::sole()->area_id)->toBe($area->id);
});

test('a client with several areas sees only their areas in the picker', function () {
    $sales = Area::factory()->create(['title' => 'Comercial']);
    $support = Area::factory()->create(['title' => 'Técnica']);
    Area::factory()->create(['title' => 'Facturación']);

    $this->actingAs(User::factory()->withAreas($sales, $support)->create());

    ticketForm()
        ->assertSee(__('Elegí para qué área es este ticket.'))
        ->assertSee('Comercial')
        ->assertSee('Técnica')
        ->assertDontSee('Facturación');
});

test('a client with several areas files for the picked one', function () {
    $sales = Area::factory()->create();
    $support = Area::factory()->create();
    $this->actingAs(User::factory()->withAreas($sales, $support)->create());

    ticketForm()
        ->set('area_id', $support->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::sole()->area_id)->toBe($support->id);
});

test('a client with several areas must pick one', function () {
    $this->actingAs(User::factory()->withAreas(Area::factory()->create(), Area::factory()->create())->create());

    ticketForm()
        ->call('save')
        ->assertHasErrors(['area_id' => 'required']);

    expect(Ticket::count())->toBe(0);
});

test('a client cannot file for an area they do not belong to', function () {
    $foreign = Area::factory()->create();
    $this->actingAs(User::factory()->withAreas(Area::factory()->create(), Area::factory()->create())->create());

    ticketForm()
        ->set('area_id', $foreign->id)
        ->call('save')
        ->assertHasErrors(['area_id']);

    expect(Ticket::count())->toBe(0);
});

test('an admin filing on behalf of a client picks among the client areas', function () {
    $sales = Area::factory()->create();
    $support = Area::factory()->create();
    $client = User::factory()->withAreas($sales, $support)->create();

    $this->actingAs(User::factory()->admin()->create());

    ticketForm()
        ->set('author_id', $client->id)
        ->set('area_id', $sales->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::sole())
        ->user_id->toBe($client->id)
        ->area_id->toBe($sales->id);
});

test('an admin filing on behalf of a client with several areas must pick one', function () {
    $client = User::factory()->withAreas(Area::factory()->create(), Area::factory()->create())->create();

    $this->actingAs(User::factory()->admin()->create());

    ticketForm()
        ->set('author_id', $client->id)
        ->call('save')
        ->assertHasErrors(['area_id' => 'required']);

    expect(Ticket::count())->toBe(0);
});

test('an admin cannot file a client ticket for one of the admin own areas', function () {
    $development = Area::factory()->create();
    $client = User::factory()->withAreas(Area::factory()->create(), Area::factory()->create())->create();

    $this->actingAs(User::factory()->admin()->withAreas($development)->create());

    ticketForm()
        ->set('author_id', $client->id)
        ->set('area_id', $development->id)
        ->call('save')
        ->assertHasErrors(['area_id']);

    expect(Ticket::count())->toBe(0);
});

test('a client with a single area gets it when an admin files on their behalf', function () {
    $support = Area::factory()->create();
    $client = User::factory()->withAreas($support)->create();

    $this->actingAs(User::factory()->admin()->withAreas(Area::factory()->create(), Area::factory()->create())->create());

    ticketForm()
        ->set('author_id', $client->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::sole()->area_id)->toBe($support->id);
});

test('changing the author clears the picked area and lists the new author areas', function () {
    $sales = Area::factory()->create(['title' => 'Comercial']);
    $support = Area::factory()->create(['title' => 'Técnica']);
    $billing = Area::factory()->create(['title' => 'Facturación']);
    $first = User::factory()->withAreas($sales, $support)->create();
    $second = User::factory()->withAreas($support, $billing)->create();

    $this->actingAs(User::factory()->admin()->create());

    ticketForm()
        ->set('author_id', $first->id)
        ->set('area_id', $sales->id)
        ->set('author_id', $second->id)
        ->assertSet('area_id', '')
        ->assertSee('Facturación')
        ->assertDontSee('Comercial');
});

test('an admin with several areas picks one of their own for their own ticket', function () {
    $development = Area::factory()->create();
    $this->actingAs(User::factory()->admin()->withAreas($development, Area::factory()->create())->create());

    ticketForm()
        ->call('save')
        ->assertHasErrors(['area_id' => 'required']);

    ticketForm()
        ->set('area_id', $development->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::sole()->area_id)->toBe($development->id);
});

test('a draft can be saved without picking an area', function () {
    $this->actingAs(User::factory()->withAreas(Area::factory()->create(), Area::factory()->create())->create());

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Borrador sin área')
        ->call('saveDraft')
        ->assertHasNoErrors();

    expect(Ticket::sole())
        ->status->toBe('draft')
        ->area_id->toBeNull();
});

test('a draft is submitted for the picked area', function () {
    $support = Area::factory()->create();
    $client = User::factory()->withAreas(Area::factory()->create(), $support)->create();
    $draft = Ticket::factory()->draft()->for($client)->create();

    $this->actingAs($client);

    ticketForm(['draft' => $draft])
        ->call('submit')
        ->assertHasErrors(['area_id' => 'required']);

    ticketForm(['draft' => $draft])
        ->set('area_id', $support->id)
        ->call('submit')
        ->assertHasNoErrors();

    expect($draft->fresh())
        ->status->toBe('open')
        ->area_id->toBe($support->id);
});

test('a draft whose area the author no longer has cannot be submitted', function () {
    $sales = Area::factory()->create();
    $client = User::factory()->withAreas($sales, Area::factory()->create(), Area::factory()->create())->create();
    $draft = Ticket::factory()->draft()->for($client)->create(['area_id' => $sales->id]);

    $client->areas()->detach($sales);

    $this->actingAs($client);

    ticketForm(['draft' => $draft])
        ->assertSet('area_id', $sales->id)
        ->call('submit')
        ->assertHasErrors(['area_id']);

    expect($draft->fresh()->status)->toBe('draft');
});

test('the ticket keeps its area after the author changes areas', function () {
    $sales = Area::factory()->create();
    $client = User::factory()->withAreas($sales)->create();

    $this->actingAs($client);

    ticketForm()->call('save')->assertHasNoErrors();

    $client->areas()->sync([Area::factory()->create()->id]);

    expect(Ticket::sole()->area_id)->toBe($sales->id);
});

test('the ticket detail shows its area', function () {
    $area = Area::factory()->create(['title' => 'Comercial']);
    $client = User::factory()->withAreas($area)->create();
    $ticket = Ticket::factory()->for($client)->create(['area_id' => $area->id]);

    $this->actingAs($client)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSee('Comercial');
});

test('the ticket detail says when a ticket has no area', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create();

    $this->actingAs($client)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSee(__('Sin área'));
});
