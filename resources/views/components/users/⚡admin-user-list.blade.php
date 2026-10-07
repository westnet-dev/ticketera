<?php

use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\Area;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use ProfileValidationRules;
    use WithPagination;

    public string $name = '';

    public string $email = '';

    public string $role = '';

    /**
     * @var array<int, string>
     */
    public array $area_ids = [];

    public ?int $editingUserId = null;

    public string $editingName = '';

    public string $editingEmail = '';

    public ?int $editingAreasUserId = null;

    /**
     * @var array<int, string>
     */
    public array $editingAreaIds = [];

    public function updatedRole(string $value): void
    {
        if ($value !== Role::Admin->value || $this->area_ids !== []) {
            return;
        }

        $defaultArea = Area::where('title', 'Desarrollo')->first();

        if ($defaultArea) {
            $this->area_ids = [(string) $defaultArea->id];
        }
    }

    public function createUser(): void
    {
        Gate::authorize('create', User::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(array_column(Role::cases(), 'value'))],
            'area_ids' => ['array'],
            'area_ids.*' => ['integer', 'distinct', 'exists:areas,id'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'password' => Str::random(40),
            ]);

            $user->areas()->sync($validated['area_ids'] ?? []);

            return $user;
        });

        Password::sendResetLink(['email' => $user->email]);

        $this->reset(['name', 'email', 'role', 'area_ids']);

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

    public function startEditingUser(int $userId): void
    {
        $target = User::findOrFail($userId);

        Gate::authorize('update', $target);

        $this->resetValidation(['editingName', 'editingEmail']);
        $this->editingUserId = $target->id;
        $this->editingName = $target->name;
        $this->editingEmail = $target->email;

        $this->modal('edit-user')->show();
    }

    /**
     * Save the user's name and email with the same rules as the profile form,
     * including dropping the verification when the email changes.
     */
    public function updateUser(): void
    {
        $target = User::findOrFail($this->editingUserId);

        Gate::authorize('update', $target);

        $validated = $this->validate([
            'editingName' => $this->nameRules(),
            'editingEmail' => $this->emailRules($target->id),
        ]);

        $target->fill([
            'name' => $validated['editingName'],
            'email' => $validated['editingEmail'],
        ]);

        if ($target->isDirty('email')) {
            $target->email_verified_at = null;
        }

        $target->save();

        $this->reset(['editingUserId', 'editingName', 'editingEmail']);

        $this->modal('edit-user')->close();
    }

    public function startEditingAreas(int $userId): void
    {
        $target = User::findOrFail($userId);

        Gate::authorize('updateArea', $target);

        $this->resetValidation('editingAreaIds');
        $this->editingAreasUserId = $target->id;
        $this->editingAreaIds = $target->areas()->pluck('areas.id')->map(fn (int $id): string => (string) $id)->all();

        $this->modal('edit-areas')->show();
    }

    /**
     * Replace the user's areas with the picked ones. Tickets already filed keep
     * the area they were filed for, so removing an area never touches them.
     */
    public function updateAreas(): void
    {
        $target = User::findOrFail($this->editingAreasUserId);

        Gate::authorize('updateArea', $target);

        $validated = $this->validate([
            'editingAreaIds' => ['array'],
            'editingAreaIds.*' => ['integer', 'distinct', 'exists:areas,id'],
        ]);

        $target->areas()->sync($validated['editingAreaIds']);

        $this->reset(['editingAreasUserId', 'editingAreaIds']);

        $this->modal('edit-areas')->close();
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
            'users' => User::query()->with(['areas' => fn ($query) => $query->orderBy('title')])->orderBy('name')->paginate(20),
            'roles' => Role::cases(),
            'areas' => Area::orderBy('title')->get(),
            'activeAdminCount' => User::activeAdminCount(),
        ];
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('Usuarios')" :subtitle="trans_choice(':count usuario registrado|:count usuarios registrados', $users->total())">
        <x-slot:actions>
            <flux:modal.trigger name="create-user">
                <flux:button variant="primary" icon="plus" class="w-full sm:w-auto">
                    {{ __('Nuevo usuario') }}
                </flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    </x-page-header>

    <x-table-panel>
        <flux:table :paginate="$users">
            <flux:table.columns>
                <flux:table.row>
                    <flux:table.column>{{ __('Usuario') }}</flux:table.column>
                    <flux:table.column>{{ __('Rol') }}</flux:table.column>
                    <flux:table.column class="hidden lg:table-cell">{{ __('Área') }}</flux:table.column>
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
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <x-user-cell :user="$user" show-email />
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:select size="sm" :disabled="$isProtected" wire:change="updateRole({{ $user->id }}, $event.target.value)">
                                @foreach ($roles as $roleOption)
                                    <flux:select.option value="{{ $roleOption->value }}" :selected="$user->role === $roleOption">
                                        {{ ucfirst($roleOption->value) }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:table.cell>
                        <flux:table.cell class="hidden lg:table-cell">
                            <div class="flex flex-wrap items-center gap-1">
                                @forelse ($user->areas as $userArea)
                                    <flux:badge size="sm">{{ $userArea->title }}</flux:badge>
                                @empty
                                    <flux:text size="sm">{{ __('Sin área') }}</flux:text>
                                @endforelse
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="startEditingAreas({{ $user->id }})" :aria-label="__('Editar áreas de :name', ['name' => $user->name])" />
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="flex flex-wrap items-center gap-2">
                            <flux:button
                                size="sm"
                                variant="outline"
                                wire:click="startEditingUser({{ $user->id }})"
                                :aria-label="__('Editar :name', ['name' => $user->name])"
                            >
                                {{ __('Editar') }}
                            </flux:button>

                            <flux:button
                                size="sm"
                                variant="outline"
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
    </x-table-panel>

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

            <flux:checkbox.group wire:model="area_ids" variant="pills" :label="__('Áreas')" :description="__('Podés elegir más de una, o ninguna.')">
                @foreach ($areas as $areaOption)
                    <flux:checkbox :key="$areaOption->id" value="{{ $areaOption->id }}" :label="$areaOption->title" />
                @endforeach
            </flux:checkbox.group>

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

    <flux:modal name="edit-user" class="max-w-lg">
        <form wire:submit="updateUser" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Editar usuario') }}</flux:heading>
                <flux:subheading>
                    {{ __('Si cambiás el email, el usuario deberá usar el nuevo para ingresar.') }}
                </flux:subheading>
            </div>

            <flux:input wire:model="editingName" :label="__('Nombre')" />

            <flux:input wire:model="editingEmail" type="email" :label="__('Email')" />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">
                    {{ __('Guardar') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="edit-areas" class="max-w-lg">
        <form wire:submit="updateAreas" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Áreas del usuario') }}</flux:heading>
                <flux:subheading>
                    {{ __('Los tickets ya creados conservan el área para la que se crearon.') }}
                </flux:subheading>
            </div>

            <flux:checkbox.group wire:model="editingAreaIds" variant="pills" :label="__('Áreas')">
                @foreach ($areas as $areaOption)
                    <flux:checkbox :key="$areaOption->id" value="{{ $areaOption->id }}" :label="$areaOption->title" />
                @endforeach
            </flux:checkbox.group>

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">
                    {{ __('Guardar') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
