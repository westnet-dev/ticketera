<?php

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $sortBy = 'created_at';

    #[Url]
    public string $sortDirection = 'desc';

    #[Url]
    public ?string $statusFilter = null;

    #[Url]
    public ?string $assignedFilter = null;

    public function filterByAssigned(?string $value): void
    {
        if ($value !== null && $value !== 'unassigned') {
            return;
        }

        $this->assignedFilter = $value;
        $this->resetPage();
    }

    public function filterByStatus(?string $status): void
    {
        if ($status !== null && ! in_array($status, Ticket::STATUSES, true)) {
            return;
        }

        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function assign(int $ticketId, ?string $userId): void
    {
        $ticket = Ticket::findOrFail($ticketId);

        Gate::authorize('assign', $ticket);

        DB::transaction(fn () => $ticket->update(['assigned_to' => $userId !== null && $userId !== '' ? $userId : null]));
    }

    public function sort(string $column): void
    {
        if (! in_array($column, ['priority', 'importance', 'urgency', 'impact', 'created_at'], true)) {
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
        $sortBy = in_array($this->sortBy, ['priority', 'importance', 'urgency', 'impact', 'created_at'], true) ? $this->sortBy : 'created_at';
        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $query = Ticket::query()
            ->approved()
            ->where('status', '!=', 'draft')
            ->with(['user', 'assignedTo', 'category']);

        if ($this->statusFilter !== null) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->statusFilter === null) {
            $query->whereNotIn('status', ['resolved']);
        }

        if ($this->assignedFilter === 'unassigned') {
            $query->unassigned();
        }

        $tickets = $query
            ->when(
                $sortBy === 'priority',
                fn ($query) => $query->orderByPriority($sortDirection),
                fn ($query) => $query->orderBy($sortBy, $sortDirection),
            )
            ->paginate(10);

        $baseQuery = Ticket::query()->approved()->where('status', '!=', 'draft');

        $countsByStatus = (clone $baseQuery)
            ->toBase()
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'tickets' => $tickets,
            'tabCounts' => [
                'all' => (int) $countsByStatus->except(['resolved'])->sum(),
                'unassigned' => (clone $baseQuery)->whereNotIn('status', ['resolved'])->unassigned()->count(),
                'resolved' => (int) ($countsByStatus['resolved'] ?? 0),
                'cancelled' => (int) ($countsByStatus['cancelled'] ?? 0),
            ],
            'sortBy' => $sortBy,
            'sortDirection' => $sortDirection,
            'statusFilter' => $this->statusFilter,
            'assignedFilter' => $this->assignedFilter,
            'assignableUsers' => User::query()
                ->where('role', Role::Admin)
                ->orderBy('name')
                ->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    <x-tabs aria-label="{{ __('Filtrar tickets') }}">
        <x-tab wire:click="filterByStatus(null); filterByAssigned(null)" :active="$statusFilter === null && $assignedFilter === null" :count="$tabCounts['all']">
            {{ __('Todos') }}
        </x-tab>
        <x-tab wire:click="filterByAssigned('unassigned'); filterByStatus(null)" :active="$assignedFilter === 'unassigned'" :count="$tabCounts['unassigned']">
            {{ __('Sin Asignar') }}
        </x-tab>
        <x-tab wire:click="filterByStatus('resolved'); filterByAssigned(null)" :active="$statusFilter === 'resolved'" :count="$tabCounts['resolved']">
            {{ __('Resueltos') }}
        </x-tab>
        <x-tab wire:click="filterByStatus('cancelled'); filterByAssigned(null)" :active="$statusFilter === 'cancelled'" :count="$tabCounts['cancelled']">
            {{ __('Cancelados') }}
        </x-tab>
    </x-tabs>

    @if ($tickets->isEmpty())
        <x-empty-state :message="__('No hay tickets.')" />
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
                        <flux:table.column>{{ __('Asignado a') }}</flux:table.column>
                    </flux:table.row>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($tickets as $ticket)
                        <flux:table.row :key="$ticket->id">
                            <flux:table.cell class="text-xs text-neutral-400">#TK-{{ $ticket->id }}</flux:table.cell>
                            <flux:table.cell class="whitespace-normal">
                                <x-tickets.subject-cell :ticket="$ticket" />
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$ticket->statusColor()">
                                    {{ $ticket->statusLabel() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <x-tickets.priority-indicator :priority="$ticket->priority" variant="solid" :ticket="$ticket" />
                            </flux:table.cell>
                            <flux:table.cell class="hidden md:table-cell">
                                <x-tickets.category-badge :category="$ticket->category" />
                            </flux:table.cell>
                            <flux:table.cell class="hidden lg:table-cell">
                                <x-user-cell :user="$ticket->user" />
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:select size="sm" wire:change="assign({{ $ticket->id }}, $event.target.value)">
                                    <flux:select.option value="" :selected="$ticket->assigned_to === null">
                                        {{ __('Sin asignar') }}
                                    </flux:select.option>
                                    @foreach ($assignableUsers as $assignable)
                                        <flux:select.option value="{{ $assignable->id }}" :selected="$ticket->assigned_to === $assignable->id">
                                            {{ $assignable->name }} ({{ $assignable->role->value }})
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-table-panel>
    @endif
</div>
