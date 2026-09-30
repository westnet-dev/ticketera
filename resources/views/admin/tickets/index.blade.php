<x-layouts::app :title="__('Tickets')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <x-page-header :title="__('Tickets')" :subtitle="__('Tickets aprobados de todos los clientes')">
            <x-slot:actions>
                <flux:button href="{{ route('ticket.create') }}" wire:navigate variant="primary" icon="plus" class="w-full sm:w-auto">
                    {{ __('Nuevo ticket') }}
                </flux:button>
            </x-slot:actions>
        </x-page-header>

        <livewire:tickets.admin-ticket-list />
    </div>
</x-layouts::app>
