<x-layouts::app :title="__('Borrador') . ': ' . $ticket->title">
    <div class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 rounded-xl">
        <x-page-header :title="__('Borrador')" :subtitle="__('Completá el ticket y envialo cuando esté listo.')">
            <x-slot:actions>
                <flux:button
                    href="{{ route('ticket.drafts') }}"
                    wire:navigate
                    variant="outline"
                    size="sm"
                    icon="arrow-left"
                >
                    {{ __('Volver a mis borradores') }}
                </flux:button>
            </x-slot:actions>
        </x-page-header>

        <livewire:tickets.create-ticket :draft="$ticket" />
    </div>
</x-layouts::app>
