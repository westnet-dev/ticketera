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
        if (! in_array($column, ['priority', 'urgency', 'impact', 'created_at'], true)) {
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
        $sortBy = in_array($this->sortBy, ['priority', 'urgency', 'impact', 'created_at'], true) ? $this->sortBy : 'created_at';
        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $query = Ticket::query()
            ->approved()
            ->where('status', '!=', 'draft')
            ->with(['user', 'assignedTo']);

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
            ->orderBy($sortBy, $sortDirection)
            ->paginate(10);

        return [
            'tickets' => $tickets,
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
    <div class="overflow-x-auto">
    <flux:button.group>
        <flux:button
            size="sm"
            :variant="$statusFilter === null && $assignedFilter === null ? 'primary' : 'filled'"
            wire:click="filterByStatus(null); filterByAssigned(null)"
        >
            {{ __('Todos') }}
        </flux:button>
        <flux:button
            size="sm"
            :variant="$assignedFilter === 'unassigned' ? 'primary' : 'filled'"
            wire:click="filterByAssigned('unassigned'); filterByStatus(null)"
        >
            {{ __('Sin Asignar') }}
        </flux:button>
        <flux:button
            size="sm"
            :variant="$statusFilter === 'resolved' ? 'primary' : 'filled'"
            wire:click="filterByStatus('resolved'); filterByAssigned(null)"
        >
            {{ __('Resueltos') }}
        </flux:button>
        <flux:button
            size="sm"
            :variant="$statusFilter === 'cancelled' ? 'primary' : 'filled'"
            wire:click="filterByStatus('cancelled'); filterByAssigned(null)"
        >
            {{ __('Cancelados') }}
        </flux:button>
    </flux:button.group>
    </div>

    @if ($tickets->isEmpty())
        <div class="flex flex-col items-center justify-center gap-2 rounded-lg border border-neutral-200 p-8 dark:border-neutral-700">
            <x-heroicon-o-ticket class="mx-auto size-32 text-neutral-400 sm:size-48" />
            <p class="text-center text-sm text-neutral-500">{{ __('No hay tickets.') }}</p>
        </div>
    @else
        <flux:table :paginate="$tickets">
            <flux:table.columns>
                <flux:table.row>
                    <flux:table.column>{{ __('ID') }}</flux:table.column>
                    <flux:table.column class="hidden lg:table-cell">{{ __('Cliente') }}</flux:table.column>
                    <flux:table.column>{{ __('Título') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'priority'" :direction="$sortDirection" wire:click="sort('priority')">
                        {{ __('Prioridad') }}
                    </flux:table.column>
                    <flux:table.column class="hidden lg:table-cell" sortable :sorted="$sortBy === 'urgency'" :direction="$sortDirection" wire:click="sort('urgency')">
                        {{ __('Urgencia') }}
                    </flux:table.column>
                    <flux:table.column class="hidden lg:table-cell" sortable :sorted="$sortBy === 'impact'" :direction="$sortDirection" wire:click="sort('impact')">
                        {{ __('Impacto') }}
                    </flux:table.column>
                    <flux:table.column>{{ __('Estado') }}</flux:table.column>
                    <flux:table.column class="hidden lg:table-cell">{{ __('Validación') }}</flux:table.column>
                    <flux:table.column class="hidden lg:table-cell" sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">
                        {{ __('Creado') }}
                    </flux:table.column>
                    <flux:table.column>{{ __('Asignado a') }}</flux:table.column>
                </flux:table.row>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($tickets as $ticket)
                    <flux:table.row :key="$ticket->id">
                        <flux:table.cell>{{ $ticket->id }}</flux:table.cell>
                        <flux:table.cell class="hidden lg:table-cell">{{ $ticket->user->name }}</flux:table.cell>
                        <flux:table.cell class="whitespace-normal">
                            <a href="{{ route('ticket.show', $ticket) }}" wire:navigate class="block min-w-40 wrap-break-word hover:underline">
                                {{ $ticket->title }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $ticket->priority }}</flux:table.cell>
                        <flux:table.cell class="hidden lg:table-cell">{{ $ticket->urgency }}</flux:table.cell>
                        <flux:table.cell class="hidden lg:table-cell">{{ $ticket->impact }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$ticket->statusColor()">
                                {{ $ticket->statusLabel() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="hidden lg:table-cell">
                            @if ($ticket->validationWasRequested())
                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm" :color="$ticket->validationStatusColor()">
                                        {{ $ticket->validationStatusLabel() }}
                                    </flux:badge>
                                    @if ($ticket->resolution_rating !== null)
                                        <span class="flex items-center gap-0.5 text-yellow-500">
                                            <flux:icon.star variant="solid" class="size-4" />
                                            {{ $ticket->resolution_rating }}
                                        </span>
                                    @endif
                                </div>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="hidden lg:table-cell">{{ $ticket->created_at->diffForHumans() }}</flux:table.cell>
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
    @endif
</div>
