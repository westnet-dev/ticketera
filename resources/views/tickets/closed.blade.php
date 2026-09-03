<x-layouts::app :title="__('Closed tickets')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <flux:heading size="lg">{{ __("Mis tickets") }}</flux:heading>

        <div class="flex items-center justify-between">
            <flux:button.group>
                <flux:button
                    variant="filled"
                    href="{{ route('ticket.index') }}"
                    wire:navigate
                >
                    {{ __("En curso") }}
                </flux:button>
                <flux:button
                    variant="primary"
                    href="{{ route('ticket.closed') }}"
                    wire:navigate
                >
                    {{ __("Cerrados") }}
                </flux:button>
            </flux:button.group>
            <flux:button
                href="{{ route('ticket.create') }}"
                wire:navigate
                variant="primary"
                icon="plus"
            >
                {{ __("Nuevo ticket") }}
            </flux:button>
        </div>

        <livewire:tickets.ticket-list status-filter="closed" />
    </div>
</x-layouts::app>
