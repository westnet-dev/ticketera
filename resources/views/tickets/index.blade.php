<x-layouts::app :title="__('My tickets')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <flux:heading size="lg">{{ __("Mis tickets") }}</flux:heading>

        <x-tickets.filter-tabs active="open" :pending-validation-count="$pendingValidationCount" />

        <livewire:tickets.ticket-list status-filter="open" />
    </div>
</x-layouts::app>
