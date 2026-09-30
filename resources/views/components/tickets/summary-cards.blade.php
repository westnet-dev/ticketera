@props([
    'ticketCounts',
])

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-stat-card
        :label="__('Total de tickets')"
        :value="$ticketCounts['total']"
        icon="ticket"
        icon-class="text-blue-500"
        :hint="trans_choice(':count nuevo esta semana|:count nuevos esta semana', $ticketCounts['created_this_week'])"
    />
    <x-stat-card
        :label="__('En curso')"
        :value="$ticketCounts['ongoing']"
        icon="clock"
        icon-class="text-green-500"
        :hint="__('Abiertos, en progreso o pausados')"
    />
    <x-stat-card
        :label="__('Por validar')"
        :value="$ticketCounts['pending_validation']"
        icon="exclamation-triangle"
        icon-class="text-amber-500"
        :hint="__('Esperan tu confirmación')"
    />
    <x-stat-card
        :label="__('Finalizados')"
        :value="$ticketCounts['finished']"
        icon="check-circle"
        icon-class="text-emerald-500"
        :hint="__('Resueltos o cancelados')"
    />
</div>
