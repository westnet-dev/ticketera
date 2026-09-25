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
    $client = User::factory()->create(['area_id' => $area->id]);
    $teammate = User::factory()->create(['area_id' => $area->id]);

    Ticket::factory()->for($teammate)->create(['status' => 'open']);
    Ticket::factory()->for($client)->create(['status' => 'resolved']);

    $this->actingAs($client)
        ->get(route('documentation.index'))
        ->assertOk()
        ->assertSee(__('Tu área tiene :count de :max tickets sin cerrar.', ['count' => 1, 'max' => 3]));
});

test('the sidebar links to the guide', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('documentation.index'));
});
