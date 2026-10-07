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

    Livewire::test('users.admin-user-list')
        ->call('startEditingUser', $other->id)
        ->assertForbidden();

    Livewire::test('users.admin-user-list')
        ->set('editingUserId', $other->id)
        ->set('editingName', 'Nombre Cambiado')
        ->set('editingEmail', 'cambiado@example.com')
        ->call('updateUser')
        ->assertForbidden();

    expect($other->fresh()->only(['name', 'email']))->toBe($other->only(['name', 'email']));
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

test('an admin can create a user with several areas', function () {
    Notification::fake();

    $sales = Area::factory()->create();
    $support = Area::factory()->create();

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('users.admin-user-list')
        ->set('name', 'Persona Multiárea')
        ->set('email', 'multiarea@example.com')
        ->set('role', 'client')
        ->set('area_ids', [(string) $sales->id, (string) $support->id])
        ->call('createUser')
        ->assertHasNoErrors();

    expect(User::where('email', 'multiarea@example.com')->sole()->areas->pluck('id')->sort()->values()->all())
        ->toBe(collect([$sales->id, $support->id])->sort()->values()->all());
});

test('an admin can create a user without areas', function () {
    Notification::fake();

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('users.admin-user-list')
        ->set('name', 'Persona Sin Área')
        ->set('email', 'sinarea@example.com')
        ->set('role', 'client')
        ->call('createUser')
        ->assertHasNoErrors();

    expect(User::where('email', 'sinarea@example.com')->sole()->areas)->toBeEmpty();
});

test('creating a user with a nonexistent area is rejected', function () {
    Notification::fake();

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('users.admin-user-list')
        ->set('name', 'Persona Inválida')
        ->set('email', 'invalida@example.com')
        ->set('role', 'client')
        ->set('area_ids', ['999999'])
        ->call('createUser')
        ->assertHasErrors(['area_ids.0']);

    expect(User::where('email', 'invalida@example.com')->exists())->toBeFalse();
});

test('an admin can add, remove and clear a user\'s areas', function () {
    $admin = User::factory()->admin()->create();
    $sales = Area::factory()->create();
    $support = Area::factory()->create();
    $target = User::factory()->withAreas($sales)->create();

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->call('startEditingAreas', $target->id)
        ->assertSet('editingAreaIds', [(string) $sales->id])
        ->set('editingAreaIds', [(string) $sales->id, (string) $support->id])
        ->call('updateAreas')
        ->assertHasNoErrors();

    expect($target->areas()->pluck('areas.id')->sort()->values()->all())
        ->toBe(collect([$sales->id, $support->id])->sort()->values()->all());

    Livewire::test('users.admin-user-list')
        ->call('startEditingAreas', $target->id)
        ->set('editingAreaIds', [(string) $support->id])
        ->call('updateAreas');

    expect($target->areas()->pluck('areas.id')->all())->toBe([$support->id]);

    Livewire::test('users.admin-user-list')
        ->call('startEditingAreas', $target->id)
        ->set('editingAreaIds', [])
        ->call('updateAreas');

    expect($target->areas()->count())->toBe(0);
});

test('updating a user\'s areas with a nonexistent area is rejected', function () {
    $area = Area::factory()->create();
    $target = User::factory()->withAreas($area)->create();

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('users.admin-user-list')
        ->call('startEditingAreas', $target->id)
        ->set('editingAreaIds', ['999999'])
        ->call('updateAreas')
        ->assertHasErrors(['editingAreaIds.0']);

    expect($target->areas()->pluck('areas.id')->all())->toBe([$area->id]);
});

test('removing an area from a user does not change the area of their tickets', function () {
    $sales = Area::factory()->create();
    $target = User::factory()->withAreas($sales)->create();
    $ticket = Ticket::factory()->for($target)->create(['area_id' => $sales->id]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('users.admin-user-list')
        ->call('startEditingAreas', $target->id)
        ->set('editingAreaIds', [])
        ->call('updateAreas');

    expect($ticket->fresh()->area_id)->toBe($sales->id);
});

test('a client cannot change a user\'s areas', function () {
    $client = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($client);

    Livewire::test('users.admin-user-list')
        ->call('startEditingAreas', $target->id)
        ->assertForbidden();

    Livewire::test('users.admin-user-list')
        ->set('editingAreasUserId', $target->id)
        ->set('editingAreaIds', [(string) Area::factory()->create()->id])
        ->call('updateAreas')
        ->assertForbidden();

    expect($target->areas()->count())->toBe(0);
});

test('the admin users list shows every area of each user', function () {
    $admin = User::factory()->admin()->create();
    $support = Area::factory()->create(['title' => 'Soporte Técnico']);
    $sales = Area::factory()->create(['title' => 'Comercial']);
    User::factory()->withAreas($support, $sales)->create();
    User::factory()->create();

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->assertSee('Soporte Técnico')
        ->assertSee('Comercial')
        ->assertSee('Sin área');
});

test('selecting the admin role pre-selects the Desarrollo area', function () {
    $admin = User::factory()->admin()->create();
    $developmentArea = Area::factory()->create(['title' => 'Desarrollo']);

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->set('role', 'admin')
        ->assertSet('area_ids', [(string) $developmentArea->id]);
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
        ->set('area_ids', [(string) $otherArea->id])
        ->call('createUser')
        ->assertHasNoErrors();

    $user = User::where('email', 'nueva.persona@example.com')->first();

    expect($user->areas->pluck('id')->all())->toBe([$otherArea->id]);
});

test('an admin can add areas on top of the pre-selected Desarrollo area', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $developmentArea = Area::factory()->create(['title' => 'Desarrollo']);
    $supportArea = Area::factory()->create(['title' => 'Técnica']);

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->set('name', 'Nueva Persona')
        ->set('email', 'nueva.persona@example.com')
        ->set('role', 'admin')
        ->set('area_ids', [(string) $developmentArea->id, (string) $supportArea->id])
        ->call('createUser')
        ->assertHasNoErrors();

    expect(User::where('email', 'nueva.persona@example.com')->sole()->areas->pluck('id')->sort()->values()->all())
        ->toBe(collect([$developmentArea->id, $supportArea->id])->sort()->values()->all());
});

test('selecting the client role does not pre-select any area', function () {
    $admin = User::factory()->admin()->create();
    Area::factory()->create(['title' => 'Desarrollo']);

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->set('role', 'client')
        ->assertSet('area_ids', []);
});

test('editing a user pre-fills the form with their current name and email', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->call('startEditingUser', $target->id)
        ->assertSet('editingUserId', $target->id)
        ->assertSet('editingName', 'Ana Pérez')
        ->assertSet('editingEmail', 'ana@example.com');
});

test('an admin can change only a user\'s name and the email verification is kept', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);
    $verifiedAt = $target->email_verified_at;

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->call('startEditingUser', $target->id)
        ->set('editingName', 'Ana María Pérez')
        ->call('updateUser')
        ->assertHasNoErrors()
        ->assertSet('editingUserId', null);

    $target->refresh();

    expect($target->name)->toBe('Ana María Pérez')
        ->and($target->email)->toBe('ana@example.com')
        ->and($target->email_verified_at?->toDateTimeString())->toBe($verifiedAt->toDateTimeString());
});

test('changing a user\'s email clears the verification and touches nothing else', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $area = Area::factory()->create();
    $target = User::factory()->withAreas($area)->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);
    $passwordHash = $target->password;
    $role = $target->role;

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->call('startEditingUser', $target->id)
        ->set('editingEmail', 'ana.perez@example.com')
        ->call('updateUser')
        ->assertHasNoErrors();

    $target->refresh();

    expect($target->email)->toBe('ana.perez@example.com')
        ->and($target->name)->toBe('Ana Pérez')
        ->and($target->email_verified_at)->toBeNull()
        ->and($target->password)->toBe($passwordHash)
        ->and($target->role)->toBe($role)
        ->and($target->areas()->pluck('areas.id')->all())->toBe([$area->id]);

    Notification::assertNothingSent();
});

test('a user logs in with the new email after an admin changes it', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create(['email' => 'ana@example.com']);

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->call('startEditingUser', $target->id)
        ->set('editingEmail', 'ana.perez@example.com')
        ->call('updateUser')
        ->assertHasNoErrors();

    auth()->logout();

    $this->post(route('login.store'), ['email' => 'ana@example.com', 'password' => 'password']);
    $this->assertGuest();

    $this->post(route('login.store'), ['email' => 'ana.perez@example.com', 'password' => 'password']);
    $this->assertAuthenticatedAs($target);
});

test('an admin can edit themselves and another admin', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->call('startEditingUser', $admin->id)
        ->set('editingName', 'Admin Renombrado')
        ->call('updateUser')
        ->assertHasNoErrors();

    Livewire::test('users.admin-user-list')
        ->call('startEditingUser', $otherAdmin->id)
        ->set('editingEmail', 'otro.admin@example.com')
        ->call('updateUser')
        ->assertHasNoErrors();

    expect($admin->fresh()->name)->toBe('Admin Renombrado')
        ->and($otherAdmin->fresh()->email)->toBe('otro.admin@example.com');
});

test('editing a user to an email another user has is rejected', function () {
    $admin = User::factory()->admin()->create();
    $existing = User::factory()->create();
    $target = User::factory()->create(['email' => 'ana@example.com']);

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->call('startEditingUser', $target->id)
        ->set('editingEmail', $existing->email)
        ->call('updateUser')
        ->assertHasErrors(['editingEmail' => 'unique']);

    expect($target->fresh()->email)->toBe('ana@example.com');
});

test('editing a user with an invalid name or email is rejected', function (string $field, string $value) {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);

    $this->actingAs($admin);

    Livewire::test('users.admin-user-list')
        ->call('startEditingUser', $target->id)
        ->set($field, $value)
        ->call('updateUser')
        ->assertHasErrors([$field]);

    expect($target->fresh()->only(['name', 'email']))->toBe(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);
})->with([
    'nombre vacío' => ['editingName', ''],
    'nombre demasiado largo' => ['editingName', str_repeat('a', 256)],
    'email vacío' => ['editingEmail', ''],
    'email mal formado' => ['editingEmail', 'no-es-un-email'],
]);
