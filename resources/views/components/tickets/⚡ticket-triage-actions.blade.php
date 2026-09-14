<?php

use App\Enums\TriageStatus;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    public Ticket $ticket;

    public string $rejectionReason = '';

    public function approve(): void
    {
        if ($this->ticket->triage_status !== TriageStatus::Pending) {
            $this->modal('approve-ticket-triage')->close();

            return;
        }

        Gate::authorize('approve', $this->ticket);

        DB::transaction(fn () => $this->ticket->update(['triage_status' => TriageStatus::Approved]));

        $this->redirect(route('ticket.show', $this->ticket), navigate: true);
    }

    public function reject(): void
    {
        if ($this->ticket->triage_status !== TriageStatus::Pending) {
            $this->modal('reject-ticket-triage')->close();

            return;
        }

        Gate::authorize('reject', $this->ticket);

        $validated = $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated) {
            $this->ticket->messages()->create([
                'user_id' => auth()->id(),
                'body' => $validated['rejectionReason'],
            ]);

            $this->ticket->update(['triage_status' => TriageStatus::Rejected]);
        });

        $this->reset(['rejectionReason']);

        $this->modal('reject-ticket-triage')->close();
    }
};
?>

<div class="flex items-center gap-2">
    <flux:modal.trigger name="approve-ticket-triage">
        <flux:button size="sm" variant="primary">
            {{ __('Aprobar') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal.trigger name="reject-ticket-triage">
        <flux:button size="sm" variant="danger">
            {{ __('Rechazar') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="approve-ticket-triage" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Aprobar ticket') }}</flux:heading>
                <flux:subheading>{{ __('El ticket saldrá de triage y quedará disponible para asignar.') }}</flux:subheading>
            </div>

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" wire:click="approve" wire:loading.attr="disabled" wire:target="approve">
                    {{ __('Confirmar aprobación') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="reject-ticket-triage" class="max-w-lg">
        <form wire:submit="reject" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Rechazar ticket') }}</flux:heading>
                <flux:subheading>{{ __('El motivo se publica como mensaje en el chat del ticket, visible para el cliente.') }}</flux:subheading>
            </div>

            <flux:field>
                <flux:label>{{ __('Motivo del rechazo') }}</flux:label>
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
