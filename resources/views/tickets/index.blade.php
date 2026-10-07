<x-layouts::app :title="__('My tickets')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <x-tickets.list-header />

        <livewire:tickets.ticket-list />
    </div>
</x-layouts::app>
