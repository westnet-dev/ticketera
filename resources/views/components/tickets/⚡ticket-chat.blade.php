<?php

use App\Models\Ticket;
use App\Rules\RichTextLength;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    public Ticket $ticket;

    public string $body = '';

    public function send(): void
    {
        Gate::authorize('reply', $this->ticket);

        $this->validate([
            'body' => ['required', 'string', new RichTextLength(min: 1, max: 2000)],
        ]);

        DB::transaction(function (): void {
            $this->ticket->messages()->create([
                'user_id' => auth()->id(),
                'body' => $this->body,
            ]);

            if ($this->isRequesterReplyToAwaitingTicket()) {
                $this->ticket->update(['status' => 'in_progress']);
            }
        });

        $this->reset('body');
    }

    /**
     * The requesting side (the author or a teammate from the ticket's area)
     * answering a ticket that was waiting on them puts it back in the team's
     * queue. This is a system transition rather than a manual status change,
     * so it does not go through the `changeStatus` gate.
     */
    private function isRequesterReplyToAwaitingTicket(): bool
    {
        return $this->ticket->isAwaitingResponse()
            && $this->ticket->isRequestedBy(auth()->user());
    }

    public function with(): array
    {
        return [
            'messages' => $this->ticket->messages()->with('user')->get(),
        ];
    }
};
?>

<div class="flex flex-1 flex-col rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900" wire:poll.5s>
    <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
        <flux:heading size="sm">{{ __('Conversación') }}</flux:heading>
    </div>

    @if ($ticket->isAwaitingResponse() && $ticket->isRequestedBy(auth()->user()))
        <div class="border-b border-neutral-200 p-4 dark:border-neutral-700">
            <flux:callout icon="chat-bubble-left-ellipsis" color="purple" :heading="__('El equipo está esperando tu respuesta para continuar.')" />
        </div>
    @endif

    <div class="flex max-h-[60vh] flex-col gap-3 overflow-y-auto p-4 lg:max-h-132">
        @forelse ($messages as $message)
            <div @class([
                'max-w-[85%] rounded-lg px-3 py-2 text-sm wrap-break-word sm:max-w-[75%]',
                'self-end bg-blue-600 text-white' => $message->user_id === auth()->id(),
                'self-start bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-100' => $message->user_id !== auth()->id(),
            ])>
                <p class="mb-1 text-xs font-semibold opacity-75">{{ $message->user->name }}</p>
                <x-tickets.rich-text :html="$message->body" :inverted="$message->user_id === auth()->id()" />
                <p class="mt-1 text-[10px] opacity-60">{{ $message->created_at->diffForHumans() }}</p>
            </div>
        @empty
            <p class="text-center text-sm text-neutral-500">{{ __('Todavía no hay mensajes en este ticket.') }}</p>
        @endforelse
    </div>

    @can('reply', $ticket)
        <form wire:submit.prevent="send" class="mt-auto flex flex-col  gap-2 border-t border-neutral-200 p-4 dark:border-neutral-700">
            <flux:field class="flex-1 relative">
                <x-tickets.rich-editor wire:model="body" compact submit-on-enter class="relative " :placeholder="__('Escribí tu mensaje... (Shift+Enter para nueva línea)')" />
                <flux:button type="submit" size="xs" variant="outline" icon="paper-airplane" class="ml-auto absolute! bottom-2 right-2" />
                <flux:error name="body" />
            </flux:field>

        </form>
    @endcan
</div>
