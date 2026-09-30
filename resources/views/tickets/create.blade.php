<x-layouts::app :title="__('Nuevo ticket')">
    <div class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 rounded-xl">
        <x-page-header :title="__('Nuevo ticket')" :subtitle="__('Contanos qué necesitás; el equipo lo revisa antes de asignarlo.')">
            <x-slot:actions>
                <flux:button
                    icon="arrow-left"
                    href="{{ route('ticket.index') }}"
                    wire:navigate
                    variant="outline"
                    size="sm"
                >
                    {{ __("Volver a tickets") }}
                </flux:button>
            </x-slot:actions>
        </x-page-header>

        <livewire:tickets.create-ticket />
    </div>
</x-layouts::app>
