<?php

use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\User;

test('the app layout lets the main area shrink so wide content never widens the page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('min-w-0', false);
});

test('the ticket detail stacks on small screens and uses a side panel on desktop', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create(['status' => 'open']);

    $this->actingAs($client)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSee('flex h-full flex-col gap-3 lg:grid lg:grid-cols-5', false)
        ->assertSeeInOrder(['order-1', 'order-3', 'order-2', 'order-4'], false);
});

test('the ticket form is single-column on mobile', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ticket.create'))
        ->assertOk()
        ->assertSee('grid grid-cols-1 gap-5 md:grid-cols-3', false)
        ->assertSee('md:col-span-3', false)
        ->assertDontSee('col-start-2', false);
});

test('client ticket list hides secondary columns and filters scroll on small screens', function () {
    $client = User::factory()->create();
    Ticket::factory()->for($client)->create(['status' => 'open']);

    $this->actingAs($client)
        ->get(route('ticket.index'))
        ->assertOk()
        ->assertSee('hidden lg:table-cell', false)
        ->assertSee('overflow-x-auto', false)
        ->assertSee('w-full sm:w-auto', false);
});

test('admin ticket, triage, user and area lists hide secondary columns below desktop', function (string $routeName) {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->create(['status' => 'open']);
    Ticket::factory()->create(['status' => 'open', 'triage_status' => TriageStatus::Pending]);

    $this->actingAs($admin)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('hidden lg:table-cell', false);
})->with(['admin.tickets', 'admin.triage', 'admin.users', 'admin.areas']);

test('dashboard metric grids adapt to the viewport', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('sm:grid-cols-2 lg:grid-cols-3', false);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('sm:grid-cols-2 lg:grid-cols-4', false);
});
