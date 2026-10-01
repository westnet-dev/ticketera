<?php

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Livewire\Livewire;

test('the migrations seed the default ticket categories', function () {
    expect(TicketCategory::orderBy('id')->pluck('name')->all())
        ->toBe(['Error', 'Nueva funcionalidad', 'Consulta']);
});

test('a client cannot view the admin categories list', function () {
    $client = User::factory()->create();

    $this->actingAs($client)
        ->get(route('admin.categories'))
        ->assertForbidden();
});

test('an admin can view the admin categories list with each category ticket count', function () {
    $admin = User::factory()->admin()->create();
    $category = TicketCategory::factory()->create(['name' => 'Infraestructura']);
    Ticket::factory()->count(2)->create(['category_id' => $category->id]);

    $this->actingAs($admin)
        ->get(route('admin.categories'))
        ->assertOk()
        ->assertSee('Infraestructura');

    Livewire::test('categories.admin-category-list')
        ->assertViewHas('categories', fn ($categories) => $categories->firstWhere('id', $category->id)->tickets_count === 2);
});

test('a client cannot perform any category management action', function () {
    $client = User::factory()->create();
    $category = TicketCategory::factory()->create();

    $this->actingAs($client);

    Livewire::test('categories.admin-category-list')
        ->set('name', 'Infraestructura')
        ->call('createCategory')
        ->assertForbidden();

    Livewire::test('categories.admin-category-list')
        ->call('startEditing', $category->id)
        ->assertForbidden();

    Livewire::test('categories.admin-category-list')
        ->call('deleteCategory', $category->id)
        ->assertForbidden();

    expect(TicketCategory::where('name', 'Infraestructura')->exists())->toBeFalse();
});

test('an admin can create a category', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('categories.admin-category-list')
        ->set('name', 'Infraestructura')
        ->call('createCategory')
        ->assertHasNoErrors();

    expect(TicketCategory::where('name', 'Infraestructura')->exists())->toBeTrue();
});

test('creating a category with a duplicate name is rejected', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('categories.admin-category-list')
        ->set('name', 'Consulta')
        ->call('createCategory')
        ->assertHasErrors(['name']);

    expect(TicketCategory::where('name', 'Consulta')->count())->toBe(1);
});

test('an admin can rename a category and the ticket keeps it', function () {
    $admin = User::factory()->admin()->create();
    $category = TicketCategory::factory()->create(['name' => 'Redes']);
    $ticket = Ticket::factory()->create(['category_id' => $category->id]);

    $this->actingAs($admin);

    Livewire::test('categories.admin-category-list')
        ->call('startEditing', $category->id)
        ->set('editingName', 'Conectividad')
        ->call('updateCategory')
        ->assertHasNoErrors();

    expect($ticket->refresh()->category->name)->toBe('Conectividad');
});

test('an admin cannot delete a category assigned to tickets', function () {
    $admin = User::factory()->admin()->create();
    $category = TicketCategory::factory()->create();
    Ticket::factory()->create(['category_id' => $category->id]);

    $this->actingAs($admin);

    Livewire::test('categories.admin-category-list')
        ->call('deleteCategory', $category->id)
        ->assertForbidden();

    expect(TicketCategory::find($category->id))->not->toBeNull();
});

test('an admin can delete a category with no tickets', function () {
    $admin = User::factory()->admin()->create();
    $category = TicketCategory::factory()->create();

    $this->actingAs($admin);

    Livewire::test('categories.admin-category-list')
        ->call('deleteCategory', $category->id);

    expect(TicketCategory::find($category->id))->toBeNull();
});
