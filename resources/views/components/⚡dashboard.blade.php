<?php

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
                'ticketsByPriority' => Ticket::query()
                    ->selectRaw('priority, count(*) as total')
                    ->groupBy('priority')
                    ->pluck('total', 'priority'),
                'unassignedCount' => Ticket::unassigned()->count(),
                'adminCount' => User::activeAdminCount(),
                'clientCount' => User::activeClientCount(),
            ];
        }

        $myTickets = Ticket::query()->forUsers([auth()->id()]);

        return [
            'isAdmin' => false,
            'openCount' => (clone $myTickets)->open()->count(),
            'closedCount' => (clone $myTickets)->closed()->count(),
            'ticketsByPriority' => (clone $myTickets)
                ->selectRaw('priority, count(*) as total')
                ->groupBy('priority')
                ->pluck('total', 'priority'),
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
                <flux:heading size="sm">{{ __('Tickets por prioridad') }}</flux:heading>
                <dl class="mt-3 flex flex-col gap-2">
                    @foreach (['urgent' => __('Urgente'), 'high' => __('Alta'), 'medium' => __('Media'), 'low' => __('Baja')] as $priority => $label)
                        <div class="flex items-center justify-between text-sm">
                            <dt class="text-neutral-500 dark:text-neutral-400">{{ $label }}</dt>
                            <dd class="font-semibold">{{ $ticketsByPriority[$priority] ?? 0 }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="sm">{{ __('Sin asignar') }}</flux:heading>
                <p class="mt-3 text-2xl font-semibold">{{ $unassignedCount }}</p>
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
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="sm">{{ __('Mis tickets abiertos') }}</flux:heading>
                <p class="mt-3 text-2xl font-semibold">{{ $openCount }}</p>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="sm">{{ __('Mis tickets cerrados') }}</flux:heading>
                <p class="mt-3 text-2xl font-semibold">{{ $closedCount }}</p>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="sm">{{ __('Mis tickets por prioridad') }}</flux:heading>
                <dl class="mt-3 flex flex-col gap-2">
                    @foreach (['low' => __('Baja'), 'medium' => __('Media'), 'high' => __('Alta'), 'urgent' => __('Urgente')] as $priority => $label)
                        <div class="flex items-center justify-between text-sm">
                            <dt class="text-neutral-500 dark:text-neutral-400">{{ $label }}</dt>
                            <dd class="font-semibold">{{ $ticketsByPriority[$priority] ?? 0 }}</dd>
                        </div>
                    @endforeach
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
