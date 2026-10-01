<?php

use App\Models\Ticket;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $statusFilter = 'open';

    #[Url]
    public string $sortBy = 'created_at';

    #[Url]
    public string $sortDirection = 'desc';

    public function sort(string $column): void
    {
        if (! in_array($column, ['priority', 'urgency', 'impact', 'created_at', 'updated_at'], true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function with(): array
    {
        $sortBy = in_array($this->sortBy, ['priority', 'urgency', 'impact', 'created_at', 'updated_at'], true) ? $this->sortBy : 'created_at';
        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $query = Ticket::query()
            ->with(['user', 'assignedTo', 'category'])
            ->listedFor(auth()->user());

        if ($this->statusFilter === 'finished') {
            $query->finished();
        } elseif ($this->statusFilter === 'draft') {
            $query->draft();
        } elseif ($this->statusFilter === 'pending_validation') {
            $query->where('user_id', auth()->id())->pendingValidation();
        } else {
            $query->ongoing();
        }

        return [
            'tickets' => $query
                ->when(
                    $sortBy === 'priority',
                    fn ($query) => $query->orderByPriority($sortDirection),
                    fn ($query) => $query->orderBy($sortBy, $sortDirection),
                )
                ->paginate(10),
            'sortBy' => $sortBy,
            'sortDirection' => $sortDirection,
        ];
    }
};
?>

<div>
    @if ($tickets->isEmpty())
        @if ($statusFilter === 'finished')
            <x-empty-state :message="__('No tienes tickets finalizados.')" />
        @elseif ($statusFilter === 'draft')
            <x-empty-state icon="pencil-square" :message="__('No tienes borradores.')" />
        @elseif ($statusFilter === 'pending_validation')
            <x-empty-state icon="check-badge" :message="__('No tenés tickets esperando tu validación.')" />
        @else
            @php($createHint = __('Haz clic en "Nuevo ticket" para crear uno.'))
            <x-empty-state :message="__('No tienes ningún ticket.')" :hint="$createHint" />
        @endif
    @else
        <x-table-panel>
            <flux:table :paginate="$tickets">
                <flux:table.columns>
                    <flux:table.row>
                        <flux:table.column class="w-24">{{ __('ID') }}</flux:table.column>
                        <flux:table.column>{{ __('Asunto') }}</flux:table.column>
                        <flux:table.column>{{ __('Estado') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'priority'" :direction="$sortDirection" wire:click="sort('priority')">
                            {{ __('Prioridad') }}
                        </flux:table.column>
                        <flux:table.column class="hidden md:table-cell">{{ __('Categoría') }}</flux:table.column>
                        <flux:table.column class="hidden lg:table-cell">{{ __('Cliente') }}</flux:table.column>
                        <flux:table.column class="hidden lg:table-cell" sortable :sorted="$sortBy === 'updated_at'" :direction="$sortDirection" wire:click="sort('updated_at')">
                            {{ __('Última actualización') }}
                        </flux:table.column>
                    </flux:table.row>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($tickets as $ticket)
                        <flux:table.row :key="$ticket->id">
                            <flux:table.cell class="text-xs text-neutral-400">#TK-{{ $ticket->id }}</flux:table.cell>
                            <flux:table.cell class="whitespace-normal">
                                <x-tickets.subject-cell :ticket="$ticket" :show-assignee="auth()->user()->isAdmin()" />
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$ticket->statusColor()">
                                    {{ $ticket->statusLabel() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <x-tickets.priority-indicator :priority="$ticket->priority" :ticket="$ticket" />
                            </flux:table.cell>
                            <flux:table.cell class="hidden md:table-cell">
                                <x-tickets.category-badge :category="$ticket->category" />
                            </flux:table.cell>
                            <flux:table.cell class="hidden lg:table-cell">
                                <x-user-cell :user="$ticket->user" />
                            </flux:table.cell>
                            <flux:table.cell class="hidden text-xs text-neutral-500 lg:table-cell dark:text-neutral-400">
                                {{ $ticket->updated_at->diffForHumans() }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-table-panel>
    @endif
</div>
