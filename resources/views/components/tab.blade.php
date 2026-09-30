@props([
    'active' => false,
    'count' => null,
])

@php
    $classes = [
        'flex shrink-0 cursor-pointer items-center gap-1.5 border-b-2 px-1 pb-3 text-sm font-medium transition-colors',
        'border-accent text-accent-content' => $active,
        'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200' => ! $active,
    ];
@endphp

{{-- Renders a link when given an href, otherwise a button (e.g. for wire:click filters). --}}
@if ($attributes->has('href'))
    <a {{ $attributes->class($classes) }} @if ($active) aria-current="page" @endif>
@else
    <button type="button" {{ $attributes->class($classes) }} @if ($active) aria-current="true" @endif>
@endif
    {{ $slot }}
    @if ($count !== null)
        <span class="text-xs font-normal text-neutral-400">{{ $count }}</span>
    @endif
@if ($attributes->has('href'))
    </a>
@else
    </button>
@endif
