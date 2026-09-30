<x-layouts::app :title="__('My tickets')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <x-tickets.list-header :ticket-counts="$ticketCounts" />

        <x-tickets.summary-cards :ticket-counts="$ticketCounts" />

        <x-tickets.filter-tabs active="open" :ticket-counts="$ticketCounts" />

        <livewire:tickets.ticket-list status-filter="open" />
    </div>
</x-layouts::app>
