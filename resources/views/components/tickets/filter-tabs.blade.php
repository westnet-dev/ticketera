@props([
    'active',
    'ticketCounts',
])

<x-tabs aria-label="{{ __('Filtrar tickets') }}">
    <x-tab :href="route('ticket.index')" wire:navigate :active="$active === 'open'" :count="$ticketCounts['ongoing']">
        {{ __('En curso') }}
    </x-tab>
    <x-tab :href="route('ticket.pending-validation')" wire:navigate :active="$active === 'pending_validation'" :count="$ticketCounts['pending_validation']">
        {{ __('Por validar') }}
    </x-tab>
    <x-tab :href="route('ticket.finished')" wire:navigate :active="$active === 'finished'" :count="$ticketCounts['finished']">
        {{ __('Finalizados') }}
    </x-tab>
    <x-tab :href="route('ticket.drafts')" wire:navigate :active="$active === 'draft'" :count="$ticketCounts['drafts']">
        {{ __('Borradores') }}
    </x-tab>
</x-tabs>
