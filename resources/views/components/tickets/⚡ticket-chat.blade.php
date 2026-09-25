<?php

use App\Models\Ticket;
use Livewire\Component;

new class extends Component
{
    public Ticket $ticket;

    public string $body = '';

    public function send(): void
    {
        $this->validate([
            'body' => 'required|string|min:1|max:2000',
        ]);

        $this->ticket->messages()->create([
            'user_id' => auth()->id(),
            'body' => $this->body,
        ]);

        $this->reset('body');
    }

    public function with(): array
    {
        return [
            'messages' => $this->ticket->messages()->with('user')->get(),
        ];
    }
};
?>

<div class="flex flex-1 flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700" wire:poll.5s>
    <div class="flex max-h-[60vh] flex-col gap-3 overflow-y-auto lg:max-h-[33rem]">
        @forelse ($messages as $message)
            <div @class([
                'max-w-[85%] rounded-lg px-3 py-2 text-sm wrap-break-word sm:max-w-[75%]',
                'self-end bg-blue-600 text-white' => $message->user_id === auth()->id(),
                'self-start bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-100' => $message->user_id !== auth()->id(),
            ])>
                <p class="mb-1 text-xs font-semibold opacity-75">{{ $message->user->name }}</p>
                <p class="whitespace-pre-line">{{ $message->body }}</p>
                <p class="mt-1 text-[10px] opacity-60">{{ $message->created_at->diffForHumans() }}</p>
            </div>
        @empty
            <p class="text-center text-sm text-neutral-500">{{ __('Todavía no hay mensajes en este ticket.') }}</p>
        @endforelse
    </div>

    <form wire:submit.prevent="send" class="mt-auto flex items-start gap-2 border-t border-neutral-200 pt-3 dark:border-neutral-700">
        <flux:field class="flex-1">
            <flux:textarea wire:model="body" rows="2" placeholder="{{ __('Escribí tu mensaje...') }}" />
            <flux:error name="body" />
        </flux:field>
        <flux:button type="submit">{{ __('Enviar') }}</flux:button>
    </form>
</div>
