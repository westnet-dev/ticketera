<x-layouts::app :title="__('Draft tickets')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <x-tickets.list-header :ticket-counts="$ticketCounts" />

        <x-tickets.summary-cards :ticket-counts="$ticketCounts" />

        <x-tickets.filter-tabs active="draft" :ticket-counts="$ticketCounts" />

        <livewire:tickets.ticket-list status-filter="draft" />
    </div>
</x-layouts::app>
