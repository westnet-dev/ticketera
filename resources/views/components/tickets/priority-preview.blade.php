@props([
    'importance',
    'urgency',
])

@php
    $priority = \App\Enums\TicketPriority::fromMatrix(
        \App\Enums\Level::tryFrom((int) $importance) ?? \App\Enums\Level::Medium,
        \App\Enums\Level::tryFrom((int) $urgency) ?? \App\Enums\Level::Medium,
    );
@endphp

<div {{ $attributes->class('flex flex-wrap items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400') }}>
    <span>{{ __('Prioridad resultante') }}:</span>
    <x-tickets.priority-indicator :priority="$priority" />
</div>
