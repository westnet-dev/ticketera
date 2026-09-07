<x-layouts::app :title="__('Borrador') . ': ' . $ticket->title">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="flex items-center justify-between gap-2">
            <flux:heading size="lg">{{ __('Borrador') }}</flux:heading>

            <flux:button
                href="{{ route('ticket.drafts') }}"
                wire:navigate
                variant="ghost"
                size="sm"
                icon="arrow-left"
            >
                {{ __('Volver a mis borradores') }}
            </flux:button>
        </div>

        <livewire:tickets.create-ticket :draft="$ticket" />
    </div>
</x-layouts::app>
