{{-- Panel that hosts a flux:table, giving every list the same compact uppercase column headings. --}}
<x-panel {{ $attributes->class('overflow-hidden px-4 pb-3 [&_th]:text-xs [&_th]:uppercase [&_th]:tracking-wide') }}>
    {{ $slot }}
</x-panel>
