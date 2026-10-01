@props([
    'counts',
])

{{-- One meter per priority level, most pressing first; `counts` maps the priority value to its ticket count. --}}
@php
    $total = $counts->sum();
@endphp

<dl {{ $attributes->class('flex flex-col gap-3 p-4') }}>
    @foreach (array_reverse(\App\Enums\TicketPriority::cases()) as $priority)
        <x-meter :value="$counts[$priority->value] ?? 0" :max="$total">
            <x-slot:label>
                <x-tickets.priority-indicator :priority="$priority" />
            </x-slot:label>
        </x-meter>
    @endforeach
</dl>
