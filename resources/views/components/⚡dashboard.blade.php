<?php

use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\User;
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
                'avgPriority' => round(Ticket::query()->avg('priority') ?? 0, 1),
                'avgUrgency' => round(Ticket::query()->avg('urgency') ?? 0, 1),
                'avgImpact' => round(Ticket::query()->avg('impact') ?? 0, 1),
                'unassignedCount' => Ticket::approved()->unassigned()->count(),
                'pendingTriageCount' => Ticket::query()->where('triage_status', TriageStatus::Pending)->count(),
                'adminCount' => User::activeAdminCount(),
                'clientCount' => User::activeClientCount(),
            ];
        }

        $myTickets = Ticket::query()->forUsers([auth()->id()]);

        return [
            'isAdmin' => false,
            'openCount' => (clone $myTickets)->open()->count(),
            'closedCount' => (clone $myTickets)->closed()->count(),
            'pendingTriageCount' => (clone $myTickets)->where('triage_status', TriageStatus::Pending)->count(),
            'avgPriority' => round((clone $myTickets)->avg('priority') ?? 0, 1),
            'avgUrgency' => round((clone $myTickets)->avg('urgency') ?? 0, 1),
            'avgImpact' => round((clone $myTickets)->avg('impact') ?? 0, 1),
            'recentTickets' => (clone $myTickets)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-6">
    @if ($isAdmin)
        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="sm">{{ __('Tickets por estado') }}</flux:heading>
                <dl class="mt-3 flex flex-col gap-2">
                    @foreach (['open' => __('Abierto'), 'in_progress' => __('En Progreso'), 'resolved' => __('Resuelto'), 'closed' => __('Cerrado')] as $status => $label)
                        <div class="flex items-center justify-between text-sm">
                            <dt class="text-neutral-500 dark:text-neutral-400">{{ $label }}</dt>
                            <dd class="font-semibold">{{ $ticketsByStatus[$status] ?? 0 }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="sm">{{ __('Promedio de prioridad, urgencia e impacto') }}</flux:heading>
                <dl class="mt-3 flex flex-col gap-2">
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">{{ __('Prioridad') }}</dt>
                        <dd class="font-semibold">{{ $avgPriority }}</dd>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">{{ __('Urgencia') }}</dt>
                        <dd class="font-semibold">{{ $avgUrgency }}</dd>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">{{ __('Impacto') }}</dt>
                        <dd class="font-semibold">{{ $avgImpact }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="sm">{{ __('Sin asignar') }}</flux:heading>
                <p class="mt-3 text-2xl font-semibold">{{ $unassignedCount }}</p>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="sm">{{ __('Pendientes de aprobación') }}</flux:heading>
                <p class="mt-3 text-2xl font-semibold">{{ $pendingTriageCount }}</p>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="sm">{{ __('Usuarios') }}</flux:heading>
                <dl class="mt-3 flex flex-col gap-2">
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">{{ __('Admins') }}</dt>
                        <dd class="font-semibold">{{ $adminCount }}</dd>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">{{ __('Clientes') }}</dt>
                        <dd class="font-semibold">{{ $clientCount }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="sm">{{ __('Mis tickets abiertos') }}</flux:heading>
                <div class="flex h-full items-center justify-center">
                    <p class="text-6xl">{{ $openCount }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="sm">{{ __('Mis tickets cerrados') }}</flux:heading>
                <div class="flex h-full items-center justify-center">
                    <p class="text-6xl">{{ $closedCount }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="sm">{{ __('Mis tickets pendientes de aprobación') }}</flux:heading>
                <div class="flex h-full items-center justify-center">
                    <p class="text-6xl">{{ $pendingTriageCount }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="sm">{{ __('Mi promedio de prioridad, urgencia e impacto') }}</flux:heading>
                <dl class="mt-4 flex flex-col gap-2">
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">{{ __('Prioridad') }}</dt>
                        <dd>{{ $avgPriority }}</dd>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">{{ __('Urgencia') }}</dt>
                        <dd>{{ $avgUrgency }}</dd>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">{{ __('Impacto') }}</dt>
                        <dd>{{ $avgImpact }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <flux:heading size="sm">{{ __('Mis tickets recientes') }}</flux:heading>

            @if ($recentTickets->isEmpty())
                <p class="mt-3 text-sm text-neutral-500 dark:text-neutral-400">{{ __('Todavía no creaste ningún ticket.') }}</p>
            @else
                <ul class="mt-3 flex flex-col gap-2">
                    @foreach ($recentTickets as $ticket)
                        <li class="flex items-center justify-between text-sm">
                            <a href="{{ route('ticket.show', $ticket) }}" wire:navigate class="hover:underline">
                                {{ $ticket->title }}
                            </a>
                            <flux:badge size="sm" :color="$ticket->statusColor()">
                                {{ $ticket->statusLabel() }}
                            </flux:badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
