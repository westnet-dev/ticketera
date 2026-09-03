<?php

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\User;
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

    public function assign(int $ticketId, ?string $userId): void
    {
        $ticket = Ticket::findOrFail($ticketId);

        Gate::authorize('assign', $ticket);

        $ticket->update(['assigned_to' => $userId !== null && $userId !== '' ? $userId : null]);
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

        $tickets = Ticket::query()
            ->approved()
            ->with(['user', 'assignedTo'])
            ->orderBy($sortBy, $sortDirection)
            ->paginate(10);

        return [
            'tickets' => $tickets,
            'sortBy' => $sortBy,
            'sortDirection' => $sortDirection,
            'assignableUsers' => User::query()
                ->where('role', Role::Admin)
                ->orderBy('name')
                ->get(),
        ];
    }
};
?>

<div>
    @if ($tickets->isEmpty())
        <div class="flex flex-col items-center justify-center gap-2 rounded-lg border border-neutral-200 p-8 dark:border-neutral-700">
            <x-heroicon-o-ticket style="width: 200px;" class="mx-auto text-neutral-400" />
            <p class="text-center text-sm text-neutral-500">{{ __('No hay tickets.') }}</p>
        </div>
    @else
        <flux:table :paginate="$tickets">
            <flux:table.columns>
                <flux:table.row>
                    <flux:table.column>{{ __('ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Cliente') }}</flux:table.column>
                    <flux:table.column>{{ __('Título') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'priority'" :direction="$sortDirection" wire:click="sort('priority')">
                        {{ __('Prioridad') }}
                    </flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'urgency'" :direction="$sortDirection" wire:click="sort('urgency')">
                        {{ __('Urgencia') }}
                    </flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'impact'" :direction="$sortDirection" wire:click="sort('impact')">
                        {{ __('Impacto') }}
                    </flux:table.column>
                    <flux:table.column>{{ __('Estado') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">
                        {{ __('Creado') }}
                    </flux:table.column>
                    <flux:table.column>{{ __('Asignado a') }}</flux:table.column>
                </flux:table.row>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($tickets as $ticket)
                    <flux:table.row :key="$ticket->id">
                        <flux:table.cell>{{ $ticket->id }}</flux:table.cell>
                        <flux:table.cell>{{ $ticket->user->name }}</flux:table.cell>
                        <flux:table.cell>
                            <a href="{{ route('ticket.show', $ticket) }}" wire:navigate class="hover:underline">
                                {{ $ticket->title }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $ticket->priority }}</flux:table.cell>
                        <flux:table.cell>{{ $ticket->urgency }}</flux:table.cell>
                        <flux:table.cell>{{ $ticket->impact }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$ticket->statusColor()">
                                {{ $ticket->statusLabel() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $ticket->created_at->diffForHumans() }}</flux:table.cell>
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
