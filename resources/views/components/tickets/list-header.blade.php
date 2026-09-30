@props([
    'ticketCounts',
])

<x-page-header :title="__('Mis tickets')">
    <x-slot:description>
        {{ trans_choice(':count en curso|:count en curso', $ticketCounts['ongoing']) }}
        @if (auth()->user()->isAdmin())
            · {{ trans_choice(':count asignado a vos|:count asignados a vos', $ticketCounts['assigned_to_me']) }}
        @endif
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
