@props([
    'priority',
    'ticket' => null,
])

<flux:badge size="sm" :color="$priority->color()">
    <span class="size-1.5 rounded-full bg-current mr-2"></span>
    {{ $priority->label() }}
</flux:badge>
