<?php

use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public function with(): array
    {
        if (auth()->user()->isAdmin()) {
            return [
                'isAdmin' => true,
                'ticketsByStatus' => Ticket::query()
                    ->selectRaw('status, count(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status'),
                'ticketsByPriority' => $this->countByPriority(Ticket::query()),
                'unassignedCount' => Ticket::approved()->unassigned()->count(),
                'pendingTriageCount' => Ticket::query()->where('triage_status', TriageStatus::Pending)->count(),
                'pendingValidationCount' => Ticket::pendingValidation()->count(),
                'avgRating' => round(Ticket::query()->whereNotNull('resolution_rating')->avg('resolution_rating') ?? 0, 1),
                'adminCount' => User::activeAdminCount(),
                'clientCount' => User::activeClientCount(),
            ];
        }

        $myTickets = Ticket::query()->forUsers([auth()->id()]);

        return [
            'isAdmin' => false,
            'openCount' => (clone $myTickets)->open()->count(),
            'finishedCount' => (clone $myTickets)->finished()->count(),
            'pendingTriageCount' => (clone $myTickets)->where('triage_status', TriageStatus::Pending)->count(),
            'pendingValidationCount' => (clone $myTickets)->pendingValidation()->count(),
            'ticketsByPriority' => $this->countByPriority(clone $myTickets),
            'recentTickets' => (clone $myTickets)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * Ticket count per priority level, keyed by the priority's value.
     *
     * @param  Builder<Ticket>  $query
     * @return Collection<int, int>
     */
    private function countByPriority(Builder $query): Collection
    {
        return $query
            ->toBase()
            ->selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->map(fn ($total) => (int) $total)
            ->sortKeys();
    }
};
?>

<div class="flex flex-col gap-6">
    @if ($isAdmin)
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat-card :label="__('Sin asignar')" :value="$unassignedCount" icon="user-minus" icon-class="text-orange-500" :hint="__('Aprobados y sin responsable')" />
            <x-stat-card :label="__('Pendientes de aprobación')" :value="$pendingTriageCount" icon="inbox-arrow-down" icon-class="text-blue-500" :hint="__('Esperan triage')" />
            <x-stat-card :label="__('Pendientes de validación')" :value="$pendingValidationCount" icon="exclamation-triangle" icon-class="text-amber-500" :hint="__('Esperan confirmación del cliente')" />
            <x-stat-card :label="__('Calificación promedio')" :value="$avgRating" icon="star" icon-class="text-yellow-500" :hint="__('Sobre 5 estrellas')" />
        </div>

        @php
            $statusTotal = $ticketsByStatus->sum();
        @endphp

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-panel :heading="__('Tickets por estado')" class="sm:col-span-2 lg:col-span-1">
                <dl class="flex flex-col gap-3 p-4">
                    @foreach (\App\Models\Ticket::STATUSES as $status)
                        <x-meter :value="$ticketsByStatus[$status] ?? 0" :max="$statusTotal">
                            <x-slot:label>
                                <flux:badge size="sm" :color="\App\Models\Ticket::colorForStatus($status)">
                                    {{ \App\Models\Ticket::labelForStatus($status) }}
                                </flux:badge>
                            </x-slot:label>
                        </x-meter>
                    @endforeach
                </dl>
            </x-panel>

            <x-panel :heading="__('Tickets por prioridad')">
                <x-tickets.priority-distribution :counts="$ticketsByPriority" />
            </x-panel>

            <x-panel :heading="__('Usuarios')">
                <dl class="flex flex-col gap-3 p-4">
                    <div class="flex items-center justify-between text-sm">
                        <dt class="flex items-center gap-2 text-neutral-500 dark:text-neutral-400">
                            <flux:icon.shield-check variant="mini" class="text-neutral-400" />
                            {{ __('Admins') }}
                        </dt>
                        <dd class="font-semibold">{{ $adminCount }}</dd>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <dt class="flex items-center gap-2 text-neutral-500 dark:text-neutral-400">
                            <flux:icon.user-group variant="mini" class="text-neutral-400" />
                            {{ __('Clientes') }}
                        </dt>
                        <dd class="font-semibold">{{ $clientCount }}</dd>
                    </div>
                </dl>
            </x-panel>
        </div>
    @else
        @if ($pendingValidationCount > 0)
            <flux:callout icon="check-badge" variant="warning">
                <flux:callout.heading>
                    {{ trans_choice('Tenés :count ticket esperando tu validación|Tenés :count tickets esperando tu validación', $pendingValidationCount, ['count' => $pendingValidationCount]) }}
                </flux:callout.heading>
                <flux:callout.text>
                    {{ __('Entrá a cada ticket para confirmar si el pedido quedó resuelto y calificar la solución.') }}
                </flux:callout.text>
                <x-slot name="actions">
                    <flux:button size="sm" href="{{ route('ticket.pending-validation') }}" wire:navigate>
                        {{ __('Ver pendientes') }}
                    </flux:button>
                </x-slot>
            </flux:callout>
        @endif

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card :label="__('Mis tickets abiertos')" :value="$openCount" icon="clock" icon-class="text-green-500" />
            <x-stat-card :label="__('Mis tickets finalizados')" :value="$finishedCount" icon="check-circle" icon-class="text-emerald-500" />
            <x-stat-card :label="__('Pendientes de aprobación')" :value="$pendingTriageCount" icon="inbox-arrow-down" icon-class="text-blue-500" />
            <x-stat-card :label="__('Por validar')" :value="$pendingValidationCount" icon="exclamation-triangle" icon-class="text-amber-500" />
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <x-panel :heading="__('Mis tickets recientes')" class="lg:col-span-2">
                <x-slot:actions>
                    <flux:button size="sm" variant="ghost" href="{{ route('ticket.index') }}" wire:navigate icon-trailing="arrow-right">
                        {{ __('Ver todos') }}
                    </flux:button>
                </x-slot:actions>

                @if ($recentTickets->isEmpty())
                    <p class="p-4 text-sm text-neutral-500 dark:text-neutral-400">{{ __('Todavía no creaste ningún ticket.') }}</p>
                @else
                    <ul class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($recentTickets as $ticket)
                            <li class="flex items-center justify-between gap-3 px-4 py-3">
                                <x-tickets.subject-cell :ticket="$ticket" class="min-w-0" />
                                <div class="flex shrink-0 items-center gap-3">
                                    <x-tickets.priority-indicator :priority="$ticket->priority" :ticket="$ticket" class="hidden sm:inline-flex" />
                                    <flux:badge size="sm" :color="$ticket->statusColor()">
                                        {{ $ticket->statusLabel() }}
                                    </flux:badge>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-panel>

            <x-panel :heading="__('Mis tickets por prioridad')">
                <x-tickets.priority-distribution :counts="$ticketsByPriority" />
            </x-panel>
        </div>

        <flux:callout icon="book-open" color="zinc">
            <flux:callout.heading>{{ __('¿Querés saber cómo funciona la plataforma?') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Ingresá a la documentación para ver toda la información.') }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" href="{{ route('documentation.index') }}" wire:navigate>
                    {{ __('Ver documentación') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @endif
</div>
