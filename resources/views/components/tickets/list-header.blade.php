<x-page-header :title="__('Mis tickets')">
    <x-slot:description>
        {{ __('Los tickets de tus áreas, uno por pestaña') }}
    </x-slot:description>

    <x-slot:actions>
        <flux:button
            href="{{ route('ticket.create') }}"
            wire:navigate
            variant="primary"
            icon="plus"
            class="w-full sm:w-auto"
        >
            {{ __('Nuevo ticket') }}
        </flux:button>
    </x-slot:actions>
</x-page-header>
