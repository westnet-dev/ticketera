<x-layouts::app :title="__('Tickets awaiting validation')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <flux:heading size="lg">{{ __("Mis tickets") }}</flux:heading>

        <x-tickets.filter-tabs active="pending_validation" :pending-validation-count="$pendingValidationCount" />

        @if ($pendingValidationCount > 0)
            <flux:callout icon="check-badge" variant="warning">
                <flux:callout.heading>{{ __('Estos tickets esperan tu confirmación') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('El equipo los dio por resueltos. Entrá a cada uno para confirmar que quedó solucionado y calificar la solución, o avisar que sigue pendiente.') }}
                </flux:callout.text>
            </flux:callout>
        @endif

        <livewire:tickets.ticket-list status-filter="pending_validation" />
    </div>
</x-layouts::app>
