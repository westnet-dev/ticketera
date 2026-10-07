<?php

use App\Concerns\FiltersByTicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use FiltersByTicketStatus;
    use WithPagination;

    #[Url]
    public string $sortBy = 'created_at';

    #[Url]
    public string $sortDirection = 'desc';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $clientFilter = '';

    #[Url(except: '')]
    public string $assignedToFilter = '';

    /**
     * The columns the listing can be sorted by, which is also what reaches the SQL.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['id', 'status', 'priority', 'difficulty', 'category', 'created_at'];

    /**
     * Filters are bound with wire:model, so they land here instead of in a
     * method where resetPage() could be called by hand.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'clientFilter', 'assignedToFilter'], true)) {
            $this->resetPage();
        }
    }

    /**
     * The admin listing never shows drafts, so it offers nothing beyond the status groups.
     *
     * @return array<string, string>
     */
    protected function statusFilterExtras(): array
    {
        return [];
    }

    public function assign(int $ticketId, ?string $userId): void
    {
        $ticket = Ticket::findOrFail($ticketId);

        Gate::authorize('assign', $ticket);

        $wantsAssignee = $userId !== null && $userId !== '';
        $assignee = $wantsAssignee ? User::assignable()->find((int) $userId) : null;

        // Only active admins can take a ticket; anything else is a tampered request.
        if ($wantsAssignee && $assignee === null) {
            return;
        }

        DB::transaction(fn () => $ticket->assignTo($assignee));
    }

    public function sort(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
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
        $sortBy = in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'created_at';
        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $selectedStatuses = $this->selectedStatuses();

        $query = Ticket::query()
            ->approved()
            ->where('tickets.status', '!=', 'draft')
            ->with(['user', 'assignedTo', 'category', 'linearLinks']);

        $this->applyStatusFilter($query, auth()->user(), $selectedStatuses);

        $query->search($this->search)
            ->forClient($this->clientFilter)
            ->assignedToUser($this->assignedToFilter);

        $sorted = match ($sortBy) {
            'priority' => $query->orderByPriority($sortDirection),
            'status' => $query->orderByStatusFlow($sortDirection),
            'difficulty' => $query->orderByDifficulty($sortDirection),
            'category' => $query->orderByCategoryName($sortDirection),
            default => $query->orderBy('tickets.'.$sortBy, $sortDirection),
        };

        return [
            'tickets' => $sorted->paginate(10),
            'selectedStatuses' => $selectedStatuses,
            'sortBy' => $sortBy,
            'sortDirection' => $sortDirection,
            'assignableUsers' => User::assignable()
                ->orderBy('name')
                ->get(),
            // Only authors with a ticket this listing can actually show, so the
            // selector never offers an option that returns nothing.
            'clients' => User::query()
                ->whereHas('tickets', fn ($query) => $query->approved()->where('status', '!=', 'draft'))
                ->orderBy('name')
                ->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    <x-panel>
        <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
            <flux:input
                class="sm:flex-1"
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                clearable
                :label="__('Buscar')"
                :placeholder="__('Título o número de ticket')"
            />

            <x-tickets.status-filter class="sm:w-56" :groups="$this->statusGroups()" :selected="$selectedStatuses" />

            <flux:select class="sm:w-56" wire:model.live="clientFilter" :label="__('Cliente')">
                <flux:select.option value="">{{ __('Todos los clientes') }}</flux:select.option>
                @foreach ($clients as $client)
                    <flux:select.option value="{{ $client->id }}">{{ $client->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select class="sm:w-56" wire:model.live="assignedToFilter" :label="__('Asignado a')">
                <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                <flux:select.option value="unassigned">{{ __('Sin asignar') }}</flux:select.option>
                @foreach ($assignableUsers as $assignable)
                    <flux:select.option value="{{ $assignable->id }}">{{ $assignable->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </x-panel>

    @if ($tickets->isEmpty())
        @php
            $hasFilters = $search !== '' || $selectedStatuses !== [] || $clientFilter !== '' || $assignedToFilter !== '';
        @endphp

        <x-empty-state :message="$hasFilters ? __('Ningún ticket coincide con los filtros aplicados.') : __('No hay tickets.')" />
    @else
        <x-table-panel>
            <flux:table :paginate="$tickets">
                <flux:table.columns>
                    <flux:table.row>
                        <flux:table.column class="w-24" sortable :sorted="$sortBy === 'id'" :direction="$sortDirection" wire:click="sort('id')">
                            {{ __('ID') }}
                        </flux:table.column>
                        <flux:table.column>{{ __('Asunto') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">
                            {{ __('Estado') }}
                        </flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'priority'" :direction="$sortDirection" wire:click="sort('priority')">
                            {{ __('Prioridad') }}
                        </flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'difficulty'" :direction="$sortDirection" wire:click="sort('difficulty')">
                            {{ __('Dificultad') }}
                        </flux:table.column>
                        <flux:table.column class="hidden md:table-cell" sortable :sorted="$sortBy === 'category'" :direction="$sortDirection" wire:click="sort('category')">
                            {{ __('Categoría') }}
                        </flux:table.column>
                        <flux:table.column class="hidden lg:table-cell">{{ __('Cliente') }}</flux:table.column>
                        <flux:table.column>{{ __('Asignado a') }}</flux:table.column>
                    </flux:table.row>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($tickets as $ticket)
                        <flux:table.row :key="$ticket->id">
                            <flux:table.cell class="text-xs text-neutral-400">#TK-{{ $ticket->id }}</flux:table.cell>
                            <flux:table.cell class="whitespace-normal">
                                <x-tickets.subject-cell :ticket="$ticket">
                                    @foreach ($ticket->linearLinks as $link)
                                        <flux:badge size="sm" :color="$link->stateColor()" :title="$link->title">
                                            {{ $link->identifier }} · {{ $link->state_name }}
                                        </flux:badge>
                                    @endforeach
                                </x-tickets.subject-cell>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$ticket->statusColor()">
                                    {{ $ticket->statusLabel() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <x-tickets.priority-indicator :priority="$ticket->priority" variant="solid" :ticket="$ticket" />
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($ticket->difficulty !== null)
                                    <flux:badge size="sm" color="zinc">{{ $ticket->difficulty->label() }}</flux:badge>
                                @else
                                    <span class="text-xs text-neutral-400">—</span>
                                @endif
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
