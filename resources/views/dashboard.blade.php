<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <x-page-header
            :title="__('Dashboard')"
            :subtitle="auth()->user()->isAdmin() ? __('Resumen general de la plataforma') : __('Resumen de tus tickets')"
        >
            <x-slot:actions>
                <flux:button href="{{ route('ticket.create') }}" wire:navigate variant="primary" icon="plus" class="w-full sm:w-auto">
                    {{ __('Nuevo ticket') }}
                </flux:button>
            </x-slot:actions>
        </x-page-header>

        <livewire:dashboard />
    </div>
</x-layouts::app>
