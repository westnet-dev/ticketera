<x-layouts::app :title="__('Finished tickets')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <flux:heading size="lg">{{ __("Mis tickets") }}</flux:heading>

        <x-tickets.filter-tabs active="finished" :pending-validation-count="$pendingValidationCount" />

        <livewire:tickets.ticket-list status-filter="finished" />
    </div>
</x-layouts::app>
