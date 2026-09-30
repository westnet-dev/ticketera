@props([
    'user',
    'showEmail' => false,
])

<div {{ $attributes->class('flex min-w-0 items-center gap-2') }}>
    <flux:avatar size="xs" circle :name="$user->name" :initials="$user->initials()" />
    <div class="min-w-0 leading-tight">
        <div class="truncate text-sm text-neutral-900 dark:text-white">{{ $user->name }}</div>
        @if ($showEmail)
            <div class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ $user->email }}</div>
        @endif
    </div>
</div>
