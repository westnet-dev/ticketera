<x-layouts::app :title="__('Triage')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <flux:heading size="lg">{{ __('Triage') }}</flux:heading>

        <livewire:tickets.admin-triage-list />
    </div>
</x-layouts::app>
