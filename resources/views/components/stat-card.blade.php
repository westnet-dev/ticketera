@props([
    'label',
    'value',
    'icon',
    'iconClass' => 'text-neutral-400',
    'hint' => null,
])

<x-panel {{ $attributes->class('p-4') }}>
    <div class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
        <flux:icon :icon="$icon" variant="mini" class="{{ $iconClass }}" />
        {{ $label }}
    </div>
    <div class="mt-2 flex items-center gap-2 text-2xl font-semibold text-neutral-900 dark:text-white">
        {{ is_int($value) ? number_format($value, 0, ',', '.') : $value }}
    </div>
    @if ($hint !== null)
        <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ $hint }}</div>
    @endif
</x-panel>
