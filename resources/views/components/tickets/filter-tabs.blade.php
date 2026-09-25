@props([
    'active',
    'pendingValidationCount' => 0,
])

<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="overflow-x-auto">
    <flux:button.group>
        <flux:button
            :variant="$active === 'open' ? 'primary' : 'filled'"
            href="{{ route('ticket.index') }}"
            wire:navigate
        >
            {{ __('En curso') }}
        </flux:button>
        <flux:button
            :variant="$active === 'pending_validation' ? 'primary' : 'filled'"
            href="{{ route('ticket.pending-validation') }}"
            wire:navigate
        >
            {{ $pendingValidationCount > 0
                ? __('Por validar (:count)', ['count' => $pendingValidationCount])
                : __('Por validar') }}
        </flux:button>
        <flux:button
            :variant="$active === 'finished' ? 'primary' : 'filled'"
            href="{{ route('ticket.finished') }}"
            wire:navigate
        >
            {{ __('Finalizados') }}
        </flux:button>
        <flux:button
            :variant="$active === 'draft' ? 'primary' : 'filled'"
            href="{{ route('ticket.drafts') }}"
            wire:navigate
        >
            {{ __('Borradores') }}
        </flux:button>
    </flux:button.group>
    </div>

    <flux:button
        href="{{ route('ticket.create') }}"
        wire:navigate
        variant="primary"
        icon="plus"
        class="w-full sm:w-auto"
    >
        {{ __('Nuevo ticket') }}
    </flux:button>
</div>
