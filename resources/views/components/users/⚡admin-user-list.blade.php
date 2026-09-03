<?php

use App\Enums\Role;
use App\Models\Area;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $name = '';

    public string $email = '';

    public string $role = '';

    public string $area_id = '';

    public function updatedRole(string $value): void
    {
        if ($value !== Role::Admin->value || $this->area_id !== '') {
            return;
        }

        $defaultArea = Area::where('title', 'Desarrollo')->first();

        if ($defaultArea) {
            $this->area_id = (string) $defaultArea->id;
        }
    }

    public function createUser(): void
    {
        Gate::authorize('create', User::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(array_column(Role::cases(), 'value'))],
            'area_id' => ['nullable', 'exists:areas,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'area_id' => $validated['area_id'] ?: null,
            'password' => Str::random(40),
        ]);

        Password::sendResetLink(['email' => $user->email]);

        $this->reset(['name', 'email', 'role', 'area_id']);

        $this->modal('create-user')->close();

        $this->resetPage();
    }

    public function updateRole(int $userId, string $role): void
    {
        $target = User::findOrFail($userId);
        $newRole = Role::from($role);

        Gate::authorize('updateRole', [$target, $newRole]);

        $target->update(['role' => $newRole]);
    }

    public function updateArea(int $userId, ?string $areaId): void
    {
        $target = User::findOrFail($userId);

        Gate::authorize('updateArea', $target);

        $target->update(['area_id' => $areaId ?: null]);
    }

    public function resetPassword(int $userId): void
    {
        $target = User::findOrFail($userId);

        Gate::authorize('resetPassword', $target);

        $target->forceFill(['password' => Str::random(40)])->save();

        Password::sendResetLink(['email' => $target->email]);
    }

    public function deleteUser(int $userId): void
    {
        $target = User::findOrFail($userId);

        Gate::authorize('delete', $target);

        $target->delete();
    }

    public function with(): array
    {
        return [
            'users' => User::query()->orderBy('name')->paginate(20),
            'roles' => Role::cases(),
            'areas' => Area::orderBy('title')->get(),
            'activeAdminCount' => User::activeAdminCount(),
        ];
    }
};
?>

<div>
    <div class="mb-4 flex items-center justify-between">
        <flux:heading size="lg">{{ __('Usuarios') }}</flux:heading>

        <flux:modal.trigger name="create-user">
            <flux:button variant="primary" icon="plus">
                {{ __('Nuevo usuario') }}
            </flux:button>
        </flux:modal.trigger>
    </div>

    <flux:table :paginate="$users">
        <flux:table.columns>
            <flux:table.row>
                <flux:table.column>{{ __('Nombre') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Rol') }}</flux:table.column>
                <flux:table.column>{{ __('Área') }}</flux:table.column>
                <flux:table.column>{{ __('Acciones') }}</flux:table.column>
            </flux:table.row>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($users as $user)
                @php
                    $isProtected = $user->id === auth()->id()
                        || ($user->isAdmin() && $activeAdminCount <= 1);
                @endphp
                <flux:table.row :key="$user->id">
                    <flux:table.cell>{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:select size="sm" :disabled="$isProtected" wire:change="updateRole({{ $user->id }}, $event.target.value)">
                            @foreach ($roles as $roleOption)
                                <flux:select.option value="{{ $roleOption->value }}" :selected="$user->role === $roleOption">
                                    {{ ucfirst($roleOption->value) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:select size="sm" wire:change="updateArea({{ $user->id }}, $event.target.value)">
                            <flux:select.option value="" :selected="is_null($user->area_id)">
                                {{ __('Sin área') }}
                            </flux:select.option>
                            @foreach ($areas as $areaOption)
                                <flux:select.option value="{{ $areaOption->id }}" :selected="$user->area_id === $areaOption->id">
                                    {{ $areaOption->title }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:table.cell>
                    <flux:table.cell class="flex items-center gap-2">
                        <flux:button
                            size="sm"
                            variant="ghost"
                            wire:click="resetPassword({{ $user->id }})"
                            wire:confirm="{{ __('¿Enviar link de reseteo de contraseña a :email?', ['email' => $user->email]) }}"
                        >
                            {{ __('Resetear contraseña') }}
                        </flux:button>

                        <flux:button
                            size="sm"
                            variant="danger"
                            :disabled="$isProtected"
                            wire:click="deleteUser({{ $user->id }})"
                            wire:confirm="{{ __('¿Eliminar a :name? Esta acción no se puede deshacer.', ['name' => $user->name]) }}"
                        >
                            {{ __('Eliminar') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="create-user" class="max-w-lg">
        <form wire:submit="createUser" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Nuevo usuario') }}</flux:heading>
                <flux:subheading>
                    {{ __('El usuario recibirá un email para definir su contraseña.') }}
                </flux:subheading>
            </div>

            <flux:input wire:model="name" :label="__('Nombre')" />

            <flux:input wire:model="email" type="email" :label="__('Email')" />

            <flux:select wire:model="role" :label="__('Rol')">
                <flux:select.option value="">{{ __('Elegir un rol') }}</flux:select.option>
                @foreach ($roles as $roleOption)
                    <flux:select.option value="{{ $roleOption->value }}">
                        {{ ucfirst($roleOption->value) }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="area_id" :label="__('Área')">
                <flux:select.option value="">{{ __('Sin área') }}</flux:select.option>
                @foreach ($areas as $areaOption)
                    <flux:select.option value="{{ $areaOption->id }}">
                        {{ $areaOption->title }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">
                    {{ __('Crear usuario') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
