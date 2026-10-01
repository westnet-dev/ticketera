<?php

use App\Models\TicketCategory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $name = '';

    public ?int $editingCategoryId = null;

    public string $editingName = '';

    public function createCategory(): void
    {
        Gate::authorize('create', TicketCategory::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('ticket_categories', 'name')],
        ]);

        TicketCategory::create($validated);

        $this->reset(['name']);

        $this->modal('create-category')->close();

        $this->resetPage();
    }

    public function startEditing(int $categoryId): void
    {
        $category = TicketCategory::findOrFail($categoryId);

        Gate::authorize('update', $category);

        $this->editingCategoryId = $category->id;
        $this->editingName = $category->name;
    }

    public function updateCategory(): void
    {
        $category = TicketCategory::findOrFail($this->editingCategoryId);

        Gate::authorize('update', $category);

        $validated = $this->validate([
            'editingName' => ['required', 'string', 'max:100', Rule::unique('ticket_categories', 'name')->ignore($category->id)],
        ]);

        $category->update(['name' => $validated['editingName']]);

        $this->reset(['editingCategoryId', 'editingName']);
    }

    public function cancelEditing(): void
    {
        $this->reset(['editingCategoryId', 'editingName']);
    }

    public function deleteCategory(int $categoryId): void
    {
        $category = TicketCategory::findOrFail($categoryId);

        Gate::authorize('delete', $category);

        $category->delete();
    }

    public function with(): array
    {
        return [
            'categories' => TicketCategory::withCount('tickets')->orderBy('name')->paginate(20),
        ];
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('Categorías')" :subtitle="__('Clasificación temática de los tickets')">
        <x-slot:actions>
            <flux:modal.trigger name="create-category">
                <flux:button variant="primary" icon="plus" class="w-full sm:w-auto">
                    {{ __('Nueva categoría') }}
                </flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    </x-page-header>

    @if ($categories->isEmpty())
        <x-empty-state icon="tag" :message="__('Todavía no hay categorías.')" />
    @else
        <x-table-panel>
            <flux:table :paginate="$categories">
                <flux:table.columns>
                    <flux:table.row>
                        <flux:table.column>{{ __('Nombre') }}</flux:table.column>
                        <flux:table.column>{{ __('Tickets') }}</flux:table.column>
                        <flux:table.column>{{ __('Acciones') }}</flux:table.column>
                    </flux:table.row>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($categories as $category)
                        <flux:table.row :key="$category->id">
                            <flux:table.cell>
                                @if ($editingCategoryId === $category->id)
                                    <form wire:submit="updateCategory" class="flex items-center gap-2">
                                        <flux:input size="sm" wire:model="editingName" />
                                        <flux:button size="sm" type="submit">{{ __('Guardar') }}</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="cancelEditing">{{ __('Cancelar') }}</flux:button>
                                    </form>
                                    <flux:error name="editingName" />
                                @else
                                    <span class="font-medium text-neutral-900 dark:text-white">{{ $category->name }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" icon="ticket">{{ $category->tickets_count }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="flex flex-wrap items-center gap-2">
                                @if ($editingCategoryId !== $category->id)
                                    <flux:button size="sm" variant="ghost" wire:click="startEditing({{ $category->id }})">
                                        {{ __('Renombrar') }}
                                    </flux:button>
                                @endif

                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    :disabled="$category->tickets_count > 0"
                                    wire:click="deleteCategory({{ $category->id }})"
                                    wire:confirm="{{ __('¿Eliminar la categoría :name?', ['name' => $category->name]) }}"
                                >
                                    {{ __('Eliminar') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-table-panel>
    @endif

    <flux:modal name="create-category" class="max-w-lg">
        <form wire:submit="createCategory" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Nueva categoría') }}</flux:heading>
            </div>

            <flux:input wire:model="name" :label="__('Nombre')" />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">
                    {{ __('Crear categoría') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
