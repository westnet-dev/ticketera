@props([
    'value',
    'max',
    'label' => null,
    'suffix' => null,
])

{{-- A labelled value with a thin proportion bar underneath; pass a `label` slot for rich labels. --}}
<div {{ $attributes }}>
    <div class="flex items-center justify-between text-sm">
        <dt class="text-neutral-500 dark:text-neutral-400">{{ $label }}</dt>
        <dd class="font-semibold">
            {{ $value }}
            @if ($suffix !== null)
                <span class="text-xs font-normal text-neutral-400">{{ $suffix }}</span>
            @endif
        </dd>
    </div>
    <div class="mt-1.5 h-1.5 rounded-full bg-neutral-100 dark:bg-neutral-800">
        <div class="h-1.5 rounded-full bg-neutral-400 dark:bg-neutral-500" style="width: {{ $max > 0 ? min(100, round($value / $max * 100)) : 0 }}%"></div>
    </div>
</div>
