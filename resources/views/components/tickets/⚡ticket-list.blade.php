<?php

use App\Concerns\FiltersByTicketStatus;
use App\Models\Area;
use App\Models\Ticket;
use App\Models\TicketSetting;
use App\Models\User;
use App\Services\Linear\LinearClient;
use App\Services\Linear\LinearUnavailableException;
use App\Services\Linear\TicketIssueCreator;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use FiltersByTicketStatus;
    use WithPagination;

    /**
     * Tab holding the requester's own tickets that sit outside their current areas.
     */
    private const TAB_NONE = 'none';

    /**
     * Admin-only tab with the tickets assigned to them.
     */
    private const TAB_ASSIGNED = 'assigned';

    /**
     * @var array<int, string>
     */
    private const SORTABLE = ['priority', 'urgency', 'impact', 'created_at', 'updated_at'];

    #[Url(except: '')]
    public string $area = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url]
    public string $sortBy = 'created_at';

    #[Url]
    public string $sortDirection = 'desc';

    /**
     * Filters are bound with wire:model, so they land here instead of in a
     * method where resetPage() could be called by hand.
     */
    public function updated(string $property): void
    {
        if ($property === 'search') {
            $this->resetPage();
        }
    }

    public function selectTab(string $key): void
    {
        $this->area = $key;
        $this->resetPage();
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

    public function createLinearIssue(int $ticketId, TicketIssueCreator $creator): void
    {
        $ticket = Ticket::findOrFail($ticketId);

        Gate::authorize('createLinearIssue', $ticket);

        try {
            $link = $creator->create($ticket, auth()->user());
        } catch (LinearUnavailableException) {
            Flux::toast(variant: 'danger', text: __('No se pudo crear el issue en Linear. Probá de nuevo en unos minutos.'));

            return;
        }

        Flux::toast(variant: 'success', text: $link->wasRecentlyCreated
            ? __('Se creó :identifier en Linear.', ['identifier' => $link->identifier])
            : __('El ticket ya estaba vinculado a :identifier.', ['identifier' => $link->identifier]));
    }

    public function with(): array
    {
        $user = auth()->user();
        $areas = $user->areas()->orderBy('title')->get();
        $tabs = $this->tabs($user, $areas);

        // Anything not offered as a tab (an area the user left, someone else's
        // area typed into the URL) falls back to the first tab.
        if (! $tabs->has($this->area)) {
            $this->area = (string) $tabs->keys()->first();
        }

        $selectedStatuses = $this->selectedStatuses();
        $sortBy = in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'created_at';
        $sortDirection = Ticket::sortDirection($this->sortDirection);

        $activeArea = $areas->firstWhere('id', (int) $this->area);

        $query = $this->tabQuery($user, $areas, $this->area)
            ->with(['user', 'assignedTo', 'category'])
            ->when($user->isAdmin(), fn (Builder $query) => $query->with('linearLinks')->withExists([
                'collaborators as is_collaborator' => fn (Builder $query) => $query->whereKey($user->id),
            ]))
            ->search($this->search);

        $this->applyStatusFilter($query, $user, $selectedStatuses);

        return [
            'tabs' => $tabs,
            'selectedStatuses' => $selectedStatuses,
            'statusExtras' => $this->statusFilterExtras(),
            'tickets' => $query
                ->when(
                    $sortBy === 'priority',
                    fn ($query) => $query->orderByPriority($sortDirection),
                    fn ($query) => $query->orderBy('tickets.'.$sortBy, $sortDirection),
                )
                ->paginate(10),
            'sortBy' => $sortBy,
            'sortDirection' => $sortDirection,
            'quota' => $this->quota($user, $areas, $activeArea),
            // Admins never validate, so the prompt is only for the requesting side.
            'canValidateListed' => $selectedStatuses === ['pending_validation'] && ! $user->isAdmin(),
            'isAdmin' => $user->isAdmin(),
            'canCreateLinearIssues' => $user->isAdmin() && app(LinearClient::class)->canCreateIssues(),
        ];
    }

    /**
     * One tab per area the user belongs to, plus the tabs that only exist for
     * some users, each with how many of its tickets are ongoing.
     *
     * @param  Collection<int, Area>  $areas
     * @return Collection<string, array{label: string, count: int}>
     */
    private function tabs(User $user, Collection $areas): Collection
    {
        $ongoingByArea = Ticket::query()
            ->visibleTo($user)
            ->ongoing()
            ->whereIn('tickets.area_id', $areas->pluck('id'))
            ->toBase()
            ->select('area_id')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('area_id')
            ->pluck('aggregate', 'area_id');

        $tabs = $areas->mapWithKeys(fn (Area $area): array => [
            (string) $area->id => ['label' => $area->title, 'count' => (int) ($ongoingByArea[$area->id] ?? 0)],
        ]);

        // A user without areas lists their own tickets here; a user with areas
        // only gets the tab when they still hold tickets outside of them.
        $ownOutsideAreas = $this->tabQuery($user, $areas, self::TAB_NONE);

        if ($areas->isEmpty() || $ownOutsideAreas->exists()) {
            $tabs->put(self::TAB_NONE, [
                'label' => $areas->isEmpty() ? __('Mis tickets') : __('Sin área'),
                'count' => (clone $ownOutsideAreas)->ongoing()->count(),
            ]);
        }

        if ($user->isAdmin()) {
            $tabs->put(self::TAB_ASSIGNED, [
                'label' => __('Asignados a mí'),
                'count' => $this->tabQuery($user, $areas, self::TAB_ASSIGNED)->ongoing()->count(),
            ]);
        }

        return $tabs;
    }

    /**
     * The tickets a tab lists before the status filter and search apply.
     *
     * @param  Collection<int, Area>  $areas
     * @return Builder<Ticket>
     */
    private function tabQuery(User $user, Collection $areas, string $tab): Builder
    {
        return match ($tab) {
            self::TAB_NONE => Ticket::query()
                ->where('tickets.user_id', $user->id)
                ->where(fn (Builder $query) => $query
                    ->whereNull('tickets.area_id')
                    ->orWhereNotIn('tickets.area_id', $areas->pluck('id'))),
            // Collaborations count as theirs too. Grouped so the orWhere cannot
            // escape the draft filter or anything chained after it.
            self::TAB_ASSIGNED => Ticket::query()
                ->where(fn (Builder $query) => $query
                    ->where('tickets.assigned_to', $user->id)
                    ->orWhereHas('collaborators', fn (Builder $query) => $query->whereKey($user->id)))
                ->where('tickets.status', '!=', 'draft'),
            default => Ticket::query()
                ->visibleTo($user)
                ->where('tickets.area_id', (int) $tab),
        };
    }

    /**
     * Own drafts and tickets awaiting the requester's validation, on top of the status groups.
     *
     * @return array<string, string>
     */
    protected function statusFilterExtras(): array
    {
        return [
            'pending_validation' => __('Por validar'),
            'draft' => __('Borradores'),
        ];
    }

    /**
     * How much of the ticket cap the active tab has used, or null where no cap
     * applies: admins are exempt, the assigned tab files nothing, and a user
     * with areas cannot file a ticket outside of them.
     *
     * @param  Collection<int, Area>  $areas
     * @return array{used: int, max: int, remaining: int}|null
     */
    private function quota(User $user, Collection $areas, ?Area $activeArea): ?array
    {
        if ($user->isAdmin()) {
            return null;
        }

        if ($activeArea === null && ! ($areas->isEmpty() && $this->area === self::TAB_NONE)) {
            return null;
        }

        return [
            'used' => $user->openTicketCountForLimit($activeArea),
            'max' => TicketSetting::current()->max_open_tickets_per_area,
            'remaining' => $user->remainingTicketSlots($activeArea),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    <x-tabs aria-label="{{ __('Áreas') }}">
        @foreach ($tabs as $key => $tab)
            <x-tab wire:key="tab-{{ $key }}" wire:click="selectTab('{{ $key }}')" :active="$area === (string) $key" :count="$tab['count']">
                {{ $tab['label'] }}
            </x-tab>
        @endforeach
    </x-tabs>

    @if ($quota !== null)
        <x-panel class="p-4">
            <x-meter :value="$quota['used']" :max="$quota['max']" :suffix="__('de :max sin cerrar', ['max' => $quota['max']])">
                <x-slot:label>
                    {{ trans_choice(':count cupo disponible|:count cupos disponibles', $quota['remaining'], ['count' => $quota['remaining']]) }}
                </x-slot:label>
            </x-meter>

            @if ($quota['remaining'] === 0)
                <flux:callout icon="exclamation-triangle" variant="warning" class="mt-3">
                    <flux:callout.text>
                        {{ __('Esta área alcanzó el máximo de tickets sin cerrar. No se pueden crear tickets nuevos para ella hasta que se resuelva o cancele alguno.') }}
                    </flux:callout.text>
                </flux:callout>
            @endif
        </x-panel>
    @endif

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

            <x-tickets.status-filter class="sm:w-56" :groups="$this->statusGroups()" :extras="$statusExtras" :selected="$selectedStatuses" />
        </div>
    </x-panel>

    @if ($canValidateListed && $tickets->isNotEmpty())
        <flux:callout icon="check-badge" variant="warning">
            <flux:callout.heading>{{ __('Estos tickets esperan tu confirmación') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('El equipo los dio por resueltos. Entrá a cada uno para confirmar que quedó solucionado y calificar la solución, o avisar que sigue pendiente.') }}
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($tickets->isEmpty())
        @if (trim($search) !== '')
            <x-empty-state :message="__('Ningún ticket coincide con los filtros aplicados.')" />
        @elseif ($selectedStatuses === \App\Models\Ticket::ONGOING_STATUSES)
            @php($createHint = __('Haz clic en "Nuevo ticket" para crear uno.'))
            <x-empty-state :message="__('No hay tickets en curso.')" :hint="$createHint" />
        @elseif ($selectedStatuses === \App\Models\Ticket::FINISHED_STATUSES)
            <x-empty-state :message="__('No hay tickets finalizados.')" />
        @elseif ($selectedStatuses === ['draft'])
            <x-empty-state icon="pencil-square" :message="__('No tienes borradores.')" />
        @elseif ($selectedStatuses === ['pending_validation'])
            <x-empty-state icon="check-badge" :message="__('No hay tickets esperando validación.')" />
        @else
            <x-empty-state :message="__('Ningún ticket coincide con los filtros aplicados.')" />
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
                                <x-tickets.subject-cell :ticket="$ticket" :show-assignee="$isAdmin">
                                    @if ($ticket->is_collaborator)
                                        <flux:badge size="sm" color="sky">{{ __('Colaborador') }}</flux:badge>
                                    @endif
                                    @if ($isAdmin)
                                        @foreach ($ticket->linearLinks as $link)
                                            <flux:badge size="sm" :color="$link->stateColor()" :title="$link->title">
                                                {{ $link->identifier }} · {{ $link->state_name }}
                                            </flux:badge>
                                        @endforeach
                                        @if ($canCreateLinearIssues && $ticket->linearLinks->isEmpty())
                                            @can('createLinearIssue', $ticket)
                                                <flux:button
                                                    size="xs"
                                                    variant="ghost"
                                                    icon="linear"
                                                    wire:click="createLinearIssue({{ $ticket->id }})"
                                                    wire:confirm="{{ __('¿Crear un issue en Linear para TK-:id?', ['id' => $ticket->id]) }}"
                                                    :tooltip="__('Crear issue en Linear')"
                                                    :aria-label="__('Crear issue en Linear para TK-:id', ['id' => $ticket->id])"
                                                />
                                            @endcan
                                        @endif
                                    @endif
                                </x-tickets.subject-cell>
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
