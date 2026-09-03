<?php

use App\Models\Area;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $title = '';

    public ?int $editingAreaId = null;

    public string $editingTitle = '';

    public function createArea(): void
    {
        Gate::authorize('create', Area::class);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255', Rule::unique('areas', 'title')],
        ]);

        Area::create($validated);

        $this->reset(['title']);

        $this->modal('create-area')->close();

        $this->resetPage();
    }

    public function startEditing(int $areaId): void
    {
        $area = Area::findOrFail($areaId);

        Gate::authorize('update', $area);

        $this->editingAreaId = $area->id;
        $this->editingTitle = $area->title;
    }

    public function updateArea(): void
    {
        $area = Area::findOrFail($this->editingAreaId);

        Gate::authorize('update', $area);

        $validated = $this->validate([
            'editingTitle' => ['required', 'string', 'max:255', Rule::unique('areas', 'title')->ignore($area->id)],
        ]);

        $area->update(['title' => $validated['editingTitle']]);

        $this->reset(['editingAreaId', 'editingTitle']);
    }

    public function cancelEditing(): void
    {
        $this->reset(['editingAreaId', 'editingTitle']);
    }

    public function deleteArea(int $areaId): void
    {
        $area = Area::withCount('users')->findOrFail($areaId);

        Gate::authorize('delete', $area);

        $area->delete();
    }

    public function assignArea(int $userId, int $areaId): void
    {
        $target = User::findOrFail($userId);

        Gate::authorize('updateArea', $target);

        $target->update(['area_id' => $areaId]);
    }

    public function with(): array
    {
        return [
            'areas' => Area::withCount('users')->orderBy('title')->paginate(20),
            'allAreas' => Area::orderBy('title')->get(),
            'unassignedUsers' => User::whereNull('area_id')->orderBy('name')->get(),
        ];
    }
};
?>

<div>
    <div class="mb-4 flex items-center justify-between">
        <flux:heading size="lg">{{ __('Áreas') }}</flux:heading>

        <flux:modal.trigger name="create-area">
            <flux:button variant="primary" icon="plus">
                {{ __('Nueva área') }}
            </flux:button>
        </flux:modal.trigger>
    </div>

    <flux:table :paginate="$areas">
        <flux:table.columns>
            <flux:table.row>
                <flux:table.column>{{ __('Título') }}</flux:table.column>
                <flux:table.column>{{ __('Usuarios asignados') }}</flux:table.column>
                <flux:table.column>{{ __('Acciones') }}</flux:table.column>
            </flux:table.row>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($areas as $area)
                <flux:table.row :key="$area->id">
                    <flux:table.cell>
                        @if ($editingAreaId === $area->id)
                            <form wire:submit="updateArea" class="flex items-center gap-2">
                                <flux:input size="sm" wire:model="editingTitle" />
                                <flux:button size="sm" type="submit">{{ __('Guardar') }}</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="cancelEditing">{{ __('Cancelar') }}</flux:button>
                            </form>
                        @else
                            {{ $area->title }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $area->users_count }}</flux:table.cell>
                    <flux:table.cell class="flex items-center gap-2">
                        @if ($editingAreaId !== $area->id)
                            <flux:button size="sm" variant="ghost" wire:click="startEditing({{ $area->id }})">
                                {{ __('Renombrar') }}
                            </flux:button>
                        @endif

                        <flux:button
                            size="sm"
                            variant="danger"
                            :disabled="$area->users_count > 0"
                            wire:click="deleteArea({{ $area->id }})"
                            wire:confirm="{{ __('¿Eliminar el área :title?', ['title' => $area->title]) }}"
                        >
                            {{ __('Eliminar') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-8 flex items-center justify-between">
        <flux:heading size="lg">{{ __('Usuarios sin área') }}</flux:heading>
    </div>

    @if ($unassignedUsers->isEmpty())
        <flux:text class="mt-2">{{ __('Todos los usuarios tienen área asignada.') }}</flux:text>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.row>
                    <flux:table.column>{{ __('Nombre') }}</flux:table.column>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('Asignar área') }}</flux:table.column>
                </flux:table.row>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($unassignedUsers as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>{{ $user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:select size="sm" wire:change="assignArea({{ $user->id }}, $event.target.value)">
                                <flux:select.option value="">{{ __('Elegir un área') }}</flux:select.option>
                                @foreach ($allAreas as $areaOption)
                                    <flux:select.option value="{{ $areaOption->id }}">
                                        {{ $areaOption->title }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="create-area" class="max-w-lg">
        <form wire:submit="createArea" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Nueva área') }}</flux:heading>
            </div>

            <flux:input wire:model="title" :label="__('Título')" />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">
                    {{ __('Crear área') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
