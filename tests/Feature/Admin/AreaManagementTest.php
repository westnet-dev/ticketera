<?php

use App\Models\Area;
use App\Models\User;
use Database\Seeders\AreaSeeder;
use Livewire\Livewire;

test('a client cannot view the admin areas list', function () {
    $client = User::factory()->create();

    $this->actingAs($client)
        ->get(route('admin.areas'))
        ->assertForbidden();
});

test('a client cannot perform any area management action', function () {
    $client = User::factory()->create();
    $area = Area::factory()->create();

    $this->actingAs($client);

    Livewire::test('areas.admin-area-list')
        ->set('title', 'Soporte Técnico')
        ->call('createArea')
        ->assertForbidden();

    Livewire::test('areas.admin-area-list')
        ->call('startEditing', $area->id)
        ->assertForbidden();

    Livewire::test('areas.admin-area-list')
        ->call('deleteArea', $area->id)
        ->assertForbidden();
});

test('an admin can create an area', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('areas.admin-area-list')
        ->set('title', 'Soporte Técnico')
        ->call('createArea')
        ->assertHasNoErrors();

    expect(Area::where('title', 'Soporte Técnico')->exists())->toBeTrue();
});

test('creating an area with a duplicate title is rejected', function () {
    $admin = User::factory()->admin()->create();
    $existing = Area::factory()->create(['title' => 'Facturación']);

    $this->actingAs($admin);

    Livewire::test('areas.admin-area-list')
        ->set('title', 'Facturación')
        ->call('createArea')
        ->assertHasErrors(['title']);

    expect(Area::where('title', $existing->title)->count())->toBe(1);
});

test('an admin can rename an area', function () {
    $admin = User::factory()->admin()->create();
    $area = Area::factory()->create(['title' => 'Comercial']);

    $this->actingAs($admin);

    Livewire::test('areas.admin-area-list')
        ->call('startEditing', $area->id)
        ->set('editingTitle', 'Ventas')
        ->call('updateArea')
        ->assertHasNoErrors();

    expect($area->refresh()->title)->toBe('Ventas');
});

test('an admin cannot delete an area with assigned users', function () {
    $admin = User::factory()->admin()->create();
    $area = Area::factory()->create();
    User::factory()->create(['area_id' => $area->id]);

    $this->actingAs($admin);

    Livewire::test('areas.admin-area-list')
        ->call('deleteArea', $area->id)
        ->assertForbidden();

    expect(Area::find($area->id))->not->toBeNull();
});

test('an admin can delete an area with no assigned users', function () {
    $admin = User::factory()->admin()->create();
    $area = Area::factory()->create();

    $this->actingAs($admin);

    Livewire::test('areas.admin-area-list')
        ->call('deleteArea', $area->id);

    expect(Area::find($area->id))->toBeNull();
});

test('the area seeder creates the Desarrollo area without duplicating it', function () {
    $this->seed(AreaSeeder::class);
    $this->seed(AreaSeeder::class);

    expect(Area::where('title', 'Desarrollo')->count())->toBe(1);
});

test('the areas admin screen lists users without an assigned area', function () {
    $admin = User::factory()->admin()->create();
    $area = Area::factory()->create();
    $withArea = User::factory()->create(['area_id' => $area->id]);
    $withoutArea = User::factory()->create(['area_id' => null]);

    $this->actingAs($admin);

    Livewire::test('areas.admin-area-list')
        ->assertSee($withoutArea->name)
        ->assertDontSee($withArea->name);
});

test('an admin can assign an area to a user from the unassigned users list', function () {
    $admin = User::factory()->admin()->create();
    $area = Area::factory()->create();
    $target = User::factory()->create(['area_id' => null]);

    $this->actingAs($admin);

    Livewire::test('areas.admin-area-list')
        ->call('assignArea', $target->id, $area->id)
        ->assertDontSee($target->name);

    expect($target->refresh()->area_id)->toBe($area->id);
});

test('a client cannot assign an area from the unassigned users list', function () {
    $client = User::factory()->create();
    $area = Area::factory()->create();
    $target = User::factory()->create(['area_id' => null]);

    $this->actingAs($client);

    Livewire::test('areas.admin-area-list')
        ->call('assignArea', $target->id, $area->id)
        ->assertForbidden();

    expect($target->refresh()->area_id)->toBeNull();
});
