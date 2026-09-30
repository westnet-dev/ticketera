@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->class('flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between') }}>
    <div class="min-w-0">
        <flux:heading size="xl" level="1" class="wrap-break-word">{{ $title }}</flux:heading>
        @if ($subtitle !== null || isset($description))
            {{-- A div rather than flux:text's <p>, so the description slot can hold badges. --}}
            <div class="mt-1 text-sm text-zinc-500 dark:text-white/70">{{ $description ?? $subtitle }}</div>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
