<x-layouts::app :title="__('Nuevo ticket')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <flux:heading size="lg">{{ __("Nuevo ticket") }}</flux:heading>

        <livewire:tickets.create-ticket />
    </div>
</x-layouts::app>
