<?php

use App\Enums\ValidationStatus;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    public Ticket $ticket;

    public ?int $rating = null;

    public string $rejectionReason = '';

    public function confirm(): void
    {
        if (! $this->ticket->awaitsValidation()) {
            $this->modal('confirm-ticket-resolution')->close();

            return;
        }

        Gate::authorize('validateResolution', $this->ticket);

        $validated = $this->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
        ]);

        DB::transaction(fn () => $this->ticket->update([
            'validation_status' => ValidationStatus::Confirmed,
            'resolution_rating' => $validated['rating'],
            'validated_at' => now(),
        ]));

        $this->redirect(route('ticket.show', $this->ticket), navigate: true);
    }

    public function reject(): void
    {
        if (! $this->ticket->awaitsValidation()) {
            $this->modal('reject-ticket-resolution')->close();

            return;
        }

        Gate::authorize('validateResolution', $this->ticket);

        $validated = $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated) {
            $this->ticket->messages()->create([
                'user_id' => auth()->id(),
                'body' => $validated['rejectionReason'],
            ]);

            $this->ticket->update([
                'validation_status' => ValidationStatus::Rejected,
                'validated_at' => now(),
                'status' => 'in_progress',
            ]);
        });

        $this->reset(['rejectionReason']);

        $this->redirect(route('ticket.show', $this->ticket), navigate: true);
    }
};
?>

<div class="flex flex-wrap items-center gap-2">
    <flux:modal.trigger name="confirm-ticket-resolution">
        <flux:button size="sm" variant="primary">
            {{ __('Confirmar resolución') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal.trigger name="reject-ticket-resolution">
        <flux:button size="sm" variant="danger">
            {{ __('No se resolvió') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="confirm-ticket-resolution" class="max-w-lg">
        <form wire:submit="confirm" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Confirmar resolución') }}</flux:heading>
                <flux:subheading>{{ __('Confirmás que el pedido quedó resuelto. Calificá de 1 a 5 la calidad de la solución.') }}</flux:subheading>
            </div>

            <flux:field>
                <flux:label>{{ __('Calificación') }}</flux:label>

                <div class="flex items-center gap-1">
                    @for ($star = 1; $star <= 5; $star++)
                        <button
                            type="button"
                            wire:click="$set('rating', {{ $star }})"
                            aria-label="{{ __(':count estrellas', ['count' => $star]) }}"
                            class="text-yellow-500 hover:text-yellow-400"
                        >
                            <flux:icon.star
                                variant="{{ $rating !== null && $star <= $rating ? 'solid' : 'outline' }}"
                                class="size-7"
                            />
                        </button>
                    @endfor
                </div>

                <flux:error name="rating" />
            </flux:field>

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="confirm">
                    {{ __('Confirmar') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="reject-ticket-resolution" class="max-w-lg">
        <form wire:submit="reject" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Rechazar la resolución') }}</flux:heading>
                <flux:subheading>{{ __('El motivo se publica como mensaje en el chat del ticket y el ticket vuelve a En Progreso.') }}</flux:subheading>
            </div>

            <flux:field>
                <flux:label>{{ __('¿Qué quedó sin resolver?') }}</flux:label>
                <flux:textarea wire:model="rejectionReason" />
                <flux:error name="rejectionReason" />
            </flux:field>

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit" wire:loading.attr="disabled" wire:target="reject">
                    {{ __('Confirmar rechazo') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
