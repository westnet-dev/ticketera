@props([
    'heading' => null,
])

{{-- White card on the gray app canvas: the base surface for tables, forms and metrics. --}}
<div {{ $attributes->class('rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900') }}>
    @if ($heading !== null || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
            <flux:heading size="sm">{{ $heading }}</flux:heading>
            {{ $actions ?? '' }}
        </div>
    @endif

    {{ $slot }}
</div>
