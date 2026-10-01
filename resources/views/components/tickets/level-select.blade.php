@props([
    'label',
    'description' => null,
])

@php
    $field = $attributes->wire('model')->value();
@endphp

<flux:field>
    <flux:label>{{ $label }}</flux:label>
    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif
    <flux:select {{ $attributes }}>
        @foreach (\App\Enums\Level::cases() as $level)
            <flux:select.option :key="$level->value" value="{{ $level->value }}">{{ $level->label() }}</flux:select.option>
        @endforeach
    </flux:select>
    <flux:error :name="$field" />
</flux:field>
