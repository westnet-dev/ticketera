@props([
    'ticket',
    'showAssignee' => false,
])

{{-- Ticket title plus a muted meta line; extra meta (badges, rating) can be passed in the slot. --}}
<div {{ $attributes }}>
    <a href="{{ route('ticket.show', $ticket) }}" wire:navigate class="block min-w-40 font-medium wrap-break-word text-neutral-900 hover:underline dark:text-white">
        {{ $ticket->title }}
    </a>
    <div class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-neutral-500 dark:text-neutral-400">
        <span>{{ __('Creado :date', ['date' => $ticket->created_at->diffForHumans()]) }}</span>
        @if ($showAssignee)
            <span>· {{ $ticket->assignedTo?->name ?? __('Sin asignar') }}</span>
        @endif
        @unless ($ticket->isTriageApproved())
            <flux:badge size="sm" :color="$ticket->triageStatusColor()">{{ $ticket->triageStatusLabel() }}</flux:badge>
        @endunless
        @if ($ticket->validationWasRequested())
            <flux:badge size="sm" :color="$ticket->validationStatusColor()">{{ $ticket->validationStatusLabel() }}</flux:badge>
        @endif
        @if ($ticket->resolution_rating !== null)
            <span class="inline-flex items-center gap-0.5 text-yellow-500">
                <flux:icon.star variant="solid" class="size-3.5" />
                {{ $ticket->resolution_rating }}
            </span>
        @endif
        {{ $slot }}
    </div>
</div>
