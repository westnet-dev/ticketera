@props([
    'category' => null,
])

@if ($category)
    <flux:badge size="sm" icon="tag" {{ $attributes }}>{{ $category->name }}</flux:badge>
@else
    <span {{ $attributes->class('text-xs text-neutral-400') }}>{{ __('Sin categoría') }}</span>
@endif
