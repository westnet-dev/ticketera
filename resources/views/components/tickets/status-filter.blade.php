@props([
    'groups' => [],
    'extras' => [],
    'selected' => [],
])

@use('App\Models\Ticket')

@php
    $fullGroup = collect($groups)->first(fn (array $group): bool => collect($group['statuses'])->sort()->values()->all() === collect($selected)->sort()->values()->all());

    $summary = match (true) {
        $selected === [] => __('Todos'),
        $fullGroup !== null => $fullGroup['label'],
        count($selected) === 1 => $extras[$selected[0]] ?? Ticket::labelForStatus($selected[0]),
        default => trans_choice(':count estado|:count estados', count($selected), ['count' => count($selected)]),
    };
@endphp

<flux:field {{ $attributes }}>
    <flux:label>{{ __('Estado') }}</flux:label>

    <flux:dropdown>
        <flux:button icon:trailing="chevron-down" class="w-full justify-between!" data-test="status-filter-trigger">
            {{ $summary }}
        </flux:button>

        <flux:menu keep-open class="min-w-56">
            @foreach ($groups as $key => $group)
                @php($isFull = array_diff($group['statuses'], $selected) === [])

                @if (! $loop->first)
                    <flux:menu.separator />
                @endif

                <flux:menu.checkbox
                    wire:key="status-group-{{ $key }}-{{ (int) $isFull }}"
                    wire:click="toggleStatusGroup('{{ $key }}')"
                    :checked="$isFull"
                    class="font-semibold"
                >
                    {{ $group['label'] }}
                </flux:menu.checkbox>

                @foreach ($group['statuses'] as $status)
                    @php($isChecked = in_array($status, $selected, true))

                    <flux:menu.checkbox
                        wire:key="status-{{ $status }}-{{ (int) $isChecked }}"
                        wire:click="toggleStatus('{{ $status }}')"
                        :checked="$isChecked"
                        class="ps-6"
                    >
                        {{ Ticket::labelForStatus($status) }}
                    </flux:menu.checkbox>
                @endforeach
            @endforeach

            @if ($extras !== [])
                <flux:menu.separator />

                @foreach ($extras as $value => $label)
                    @php($isChecked = in_array($value, $selected, true))

                    <flux:menu.checkbox
                        wire:key="status-{{ $value }}-{{ (int) $isChecked }}"
                        wire:click="toggleStatus('{{ $value }}')"
                        :checked="$isChecked"
                    >
                        {{ $label }}
                    </flux:menu.checkbox>
                @endforeach
            @endif
        </flux:menu>
    </flux:dropdown>
</flux:field>
