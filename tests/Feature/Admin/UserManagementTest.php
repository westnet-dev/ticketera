<?php

use App\Models\Area;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('a client cannot view the admin users list', function () {
    $client = User::factory()->create();

    $this->actingAs($client)
        ->get(route('admin.users'))
        ->assertForbidden();
});

test('a client cannot perform any user management action', function () {
    $client = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($client);

    Livewire::test('users.admin-user-list')
        ->set('name', 'Nuevo Usuario')
        ->set('email', 'nuevo@example.com')
        ->set('role', 'admin')
        ->call('createUser')
        ->assertForbidden();

    Livewire::test('users.admin-user-list')
        ->call('updateRole', $other->id, 'admin')
        ->assertForbidden();

    Livewire::test('users.admin-user-list')
        ->call('resetPassword', $other->id)
        ->assertForbidden();

    Livewire::test('users.admin-user-list')
        ->call('deleteUser', $other->id)
        ->assertForbidden();
});

test('an admin can create a user and a reset-password link is sent', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->set('name', 'Nueva Persona')
        ->set('email', 'nueva.persona@example.com')
        ->set('role', 'admin')
        ->call('createUser')
        ->assertHasNoErrors();

    $user = User::where('email', 'nueva.persona@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->isAdmin())->toBeTrue();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('creating a user with a duplicate email is rejected', function () {
    $admin = User::factory()->admin()->create();
    $existing = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->set('name', 'Otra Persona')
        ->set('email', $existing->email)
        ->set('role', 'client')
        ->call('createUser')
        ->assertHasErrors(['email']);

    expect(User::where('email', $existing->email)->count())->toBe(1);
});

test('an admin can change another user\'s role', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->call('updateRole', $target->id, 'admin');

    expect($target->refresh()->isAdmin())->toBeTrue();
});

test('an admin cannot demote themselves', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->call('updateRole', $admin->id, 'client')
        ->assertForbidden();

    expect($admin->refresh()->isAdmin())->toBeTrue();
});

test('an admin cannot demote the last remaining admin', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $otherAdmin->delete();

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->call('updateRole', $admin->id, 'client')
        ->assertForbidden();

    expect($admin->refresh()->isAdmin())->toBeTrue();
});

test('an admin can reset another user\'s password', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();
    $originalPassword = $target->password;

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->call('resetPassword', $target->id);

    expect($target->refresh()->password)->not->toBe($originalPassword);

    Notification::assertSentTo($target, ResetPassword::class);
});

test('an admin can delete another user and their tickets remain intact', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $target->id]);

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->call('deleteUser', $target->id);

    expect(User::find($target->id))->toBeNull();
    expect(User::withTrashed()->find($target->id))->not->toBeNull();
    expect($ticket->refresh()->user_id)->toBe($target->id);
});

test('an admin cannot delete themselves', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->call('deleteUser', $admin->id)
        ->assertForbidden();

    expect(User::find($admin->id))->not->toBeNull();
});

test('an admin cannot delete the last remaining admin', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $otherAdmin->delete();

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->call('deleteUser', $admin->id)
        ->assertForbidden();

    expect(User::find($admin->id))->not->toBeNull();
});

test('an admin can assign, change and unassign a user\'s area', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();
    $area = Area::factory()->create();
    $otherArea = Area::factory()->create();

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->call('updateArea', $target->id, (string) $area->id);

    expect($target->refresh()->area_id)->toBe($area->id);

    Livewire::test('users.admin-user-list')
        ->call('updateArea', $target->id, (string) $otherArea->id);

    expect($target->refresh()->area_id)->toBe($otherArea->id);

    Livewire::test('users.admin-user-list')
        ->call('updateArea', $target->id, '');

    expect($target->refresh()->area_id)->toBeNull();
});

test('a client cannot change a user\'s area', function () {
    $client = User::factory()->create();
    $target = User::factory()->create();
    $area = Area::factory()->create();

    $this->actingAs($client);

    Livewire::test('users.admin-user-list')
        ->call('updateArea', $target->id, (string) $area->id)
        ->assertForbidden();

    expect($target->refresh()->area_id)->toBeNull();
});

test('the admin users list shows each user\'s assigned area', function () {
    $admin = User::factory()->admin()->create();
    $area = Area::factory()->create(['title' => 'Soporte Técnico']);
    User::factory()->create(['area_id' => $area->id]);
    User::factory()->create(['area_id' => null]);

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->assertSee('Soporte Técnico')
        ->assertSee('Sin área');
});

test('selecting the admin role pre-selects the Desarrollo area', function () {
    $admin = User::factory()->admin()->create();
    $developmentArea = Area::factory()->create(['title' => 'Desarrollo']);

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->set('role', 'admin')
        ->assertSet('area_id', (string) $developmentArea->id);
});

test('an admin can override the pre-selected Desarrollo area before creating an admin user', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    Area::factory()->create(['title' => 'Desarrollo']);
    $otherArea = Area::factory()->create(['title' => 'Comercial']);

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->set('name', 'Nueva Persona')
        ->set('email', 'nueva.persona@example.com')
        ->set('role', 'admin')
        ->set('area_id', (string) $otherArea->id)
        ->call('createUser')
        ->assertHasNoErrors();

    $user = User::where('email', 'nueva.persona@example.com')->first();

    expect($user->area_id)->toBe($otherArea->id);
});

test('selecting the client role does not pre-select any area', function () {
    $admin = User::factory()->admin()->create();
    Area::factory()->create(['title' => 'Desarrollo']);

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->set('role', 'client')
        ->assertSet('area_id', '');
});
