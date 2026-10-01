@props([
    'placeholder' => '',
    'submitOnEnter' => false,
    'toolbar' => true,
    'compact' => false,
])

<div
    x-data="richEditor({ placeholder: @js($placeholder), submitOnEnter: @js($submitOnEnter) })"
    x-modelable="value"
    {{ $attributes->class([
        'w-full overflow-hidden rounded-lg border shadow-xs',
        'border-zinc-200 border-b-zinc-300/80 bg-white',
        'dark:border-white/10 dark:bg-white/10',
        'focus-within:border-zinc-300 dark:focus-within:border-white/20',
    ]) }}
    data-flux-control
>
    @if ($toolbar)
        <div class="flex flex-wrap items-center gap-0.5 border-b border-zinc-200 p-1 dark:border-white/10">
            <flux:button size="sm" variant="ghost" icon="bold" square
                x-on:click="run('toggleBold')"
                x-bind:class="isActive('bold') && 'bg-zinc-800/10 dark:bg-white/15'" />
            <flux:button size="sm" variant="ghost" icon="italic" square
                x-on:click="run('toggleItalic')"
                x-bind:class="isActive('italic') && 'bg-zinc-800/10 dark:bg-white/15'" />
            <flux:button size="sm" variant="ghost" icon="strikethrough" square
                x-on:click="run('toggleStrike')"
                x-bind:class="isActive('strike') && 'bg-zinc-800/10 dark:bg-white/15'" />

            <flux:separator vertical class="mx-1 my-1.5" />

            <flux:button size="sm" variant="ghost" icon="list-bullet" square
                x-on:click="run('toggleBulletList')"
                x-bind:class="isActive('bulletList') && 'bg-zinc-800/10 dark:bg-white/15'" />
            <flux:button size="sm" variant="ghost" icon="numbered-list" square
                x-on:click="run('toggleOrderedList')"
                x-bind:class="isActive('orderedList') && 'bg-zinc-800/10 dark:bg-white/15'" />

            <flux:separator vertical class="mx-1 my-1.5" />

            <flux:button size="sm" variant="ghost" icon="code-bracket" square
                x-on:click="run('toggleCodeBlock')"
                x-bind:class="isActive('codeBlock') && 'bg-zinc-800/10 dark:bg-white/15'" />
            <flux:button size="sm" variant="ghost" icon="link" square
                x-on:click="setLink()"w
                x-bind:class="isActive('link') && 'bg-zinc-800/10 dark:bg-white/15'" />

            {{ $actions ?? '' }}
        </div>
    @endif

    {{-- wire:ignore so Livewire's DOM morphing never touches ProseMirror's DOM --}}
    <div x-ref="content" wire:ignore @class([
        'mr-5 text-sm text-zinc-800 dark:text-white',
        '[&_.tiptap]:max-h-96 [&_.tiptap]:overflow-y-auto',
        $compact ? '[&_.tiptap]:min-h-16' : '[&_.tiptap]:min-h-32',
    ])></div>

    {{ $footer ?? '' }}
</div>