@props([
    'ticket',
])

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 text-xs font-medium',
    'text-red-600 dark:text-red-400' => $ticket->priorityColor() === 'red',
    'text-orange-600 dark:text-orange-400' => $ticket->priorityColor() === 'orange',
    'text-neutral-500 dark:text-neutral-400' => $ticket->priorityColor() === 'zinc',
]) }} title="{{ __('Prioridad :value/10', ['value' => $ticket->priority]) }}">
    <span class="size-1.5 rounded-full bg-current"></span>
    {{ $ticket->priorityLabel() }}
</span>
