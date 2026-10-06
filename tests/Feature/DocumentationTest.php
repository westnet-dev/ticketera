<?php

use App\Models\Area;
use App\Models\Ticket;
use App\Models\TicketSetting;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('documentation.index'))->assertRedirect(route('login'));
});

test('a client sees the user guide without the admin section', function () {
    $client = User::factory()->create();

    $this->actingAs($client)
        ->get(route('documentation.index'))
        ->assertOk()
        ->assertSee(__('Guía de uso de la plataforma'))
        ->assertSee(__('Ciclo de vida de un ticket'))
        ->assertDontSee(__('Guía para administradores'));
});

test('the guide explains the awaiting response status', function () {
    $client = User::factory()->create();

    $this->actingAs($client)
        ->get(route('documentation.index'))
        ->assertOk()
        ->assertSee(__('Esperando respuesta'))
        ->assertSee(__('El equipo necesita algo de vos para seguir'), false)
        ->assertDontSee(__('mientras se espera información'));
});

test('an admin also sees the admin section', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('documentation.index'))
        ->assertOk()
        ->assertSee(__('Guía para administradores'));
});

test('the guide quotes the configured ticket cap and the area usage', function () {
    TicketSetting::current()->update(['max_open_tickets_per_area' => 3]);

    $area = Area::factory()->create();
    $client = User::factory()->withAreas($area)->create();
    $teammate = User::factory()->withAreas($area)->create();

    Ticket::factory()->for($teammate)->create(['area_id' => $area->id, 'status' => 'open']);
    Ticket::factory()->for($client)->create(['area_id' => $area->id, 'status' => 'resolved']);

    $this->actingAs($client)
        ->get(route('documentation.index'))
        ->assertOk()
        ->assertSee(__('El área :area tiene :count de :max tickets sin cerrar.', ['area' => $area->title, 'count' => 1, 'max' => 3]));
});

test('the guide quotes the usage of every area the user belongs to', function () {
    TicketSetting::current()->update(['max_open_tickets_per_area' => 3]);

    $sales = Area::factory()->create(['title' => 'Comercial']);
    $support = Area::factory()->create(['title' => 'Técnica']);
    $client = User::factory()->withAreas($sales, $support)->create();

    Ticket::factory()->count(2)->for($client)->create(['area_id' => $sales->id, 'status' => 'open']);

    $this->actingAs($client)
        ->get(route('documentation.index'))
        ->assertOk()
        ->assertSee(__('El área :area tiene :count de :max tickets sin cerrar.', ['area' => 'Comercial', 'count' => 2, 'max' => 3]))
        ->assertSee(__('El área :area tiene :count de :max tickets sin cerrar.', ['area' => 'Técnica', 'count' => 0, 'max' => 3]));
});

test('the sidebar links to the guide', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('documentation.index'));
});
