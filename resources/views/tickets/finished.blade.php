<x-layouts::app :title="__('Finished tickets')">
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
                    href="{{ route('ticket.finished') }}"
                    wire:navigate
                >
                    {{ __("Finalizados") }}
                </flux:button>
                <flux:button
                    variant="filled"
                    href="{{ route('ticket.drafts') }}"
                    wire:navigate
                >
                    {{ __("Borradores") }}
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

        <livewire:tickets.ticket-list status-filter="finished" />
    </div>
</x-layouts::app>
