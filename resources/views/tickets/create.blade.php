<x-layouts::app :title="__('Nuevo ticket')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading size="lg">{{ __("Nuevo ticket") }}</flux:heading>

            <flux:button
                icon="arrow-left"
                href="{{ route('ticket.index') }}"
                class="w-fit"
            >
                {{ __("Volver a tickets") }}
            </flux:button>
        </div>

        <livewire:tickets.create-ticket />
    </div>
</x-layouts::app>
