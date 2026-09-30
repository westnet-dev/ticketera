@props([
    'icon' => 'ticket',
    'message',
    'hint' => null,
])

<x-panel {{ $attributes->class('flex flex-col items-center justify-center gap-2 p-10') }}>
    <flux:icon :icon="$icon" class="size-12 text-neutral-300 dark:text-neutral-600" />
    <p class="text-center text-sm text-neutral-500 dark:text-neutral-400">{{ $message }}</p>
    @if ($hint !== null)
        <p class="text-center text-xs text-neutral-400">{{ $hint }}</p>
    @endif
    {{ $slot }}
</x-panel>
