<?php

use App\Enums\TriageStatus;
use App\Models\Ticket;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ?int $rejectingTicketId = null;

    public string $rejectionReason = '';

    public function approve(int $ticketId): void
    {
        $ticket = Ticket::findOrFail($ticketId);

        Gate::authorize('approve', $ticket);

        $ticket->update(['triage_status' => TriageStatus::Approved]);
    }

    public function startRejecting(int $ticketId): void
    {
        $ticket = Ticket::findOrFail($ticketId);

        Gate::authorize('reject', $ticket);

        $this->rejectingTicketId = $ticket->id;
    }

    public function cancelRejecting(): void
    {
        $this->reset(['rejectingTicketId', 'rejectionReason']);
    }

    public function reject(): void
    {
        $ticket = Ticket::findOrFail($this->rejectingTicketId);

        Gate::authorize('reject', $ticket);

        $validated = $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $ticket->messages()->create([
            'user_id' => auth()->id(),
            'body' => $validated['rejectionReason'],
        ]);

        $ticket->update(['triage_status' => TriageStatus::Rejected]);

        $this->reset(['rejectingTicketId', 'rejectionReason']);
    }

    public function with(): array
    {
        return [
            'tickets' => Ticket::query()
                ->where('triage_status', TriageStatus::Pending)
                ->where('status', '!=', 'draft')
                ->with('user')
                ->orderBy('created_at')
                ->paginate(10),
        ];
    }
};
?>

<div>
    @if ($tickets->isEmpty())
        <div class="flex flex-col items-center justify-center gap-2 rounded-lg border border-neutral-200 p-8 dark:border-neutral-700">
            <p class="text-center text-sm text-neutral-500">{{ __('No hay tickets pendientes de triage.') }}</p>
        </div>
    @else
        <flux:table :paginate="$tickets">
            <flux:table.columns>
                <flux:table.row>
                    <flux:table.column>{{ __('ID') }}</flux:table.column>
                    <flux:table.column class="hidden lg:table-cell">{{ __('Cliente') }}</flux:table.column>
                    <flux:table.column>{{ __('Título') }}</flux:table.column>
                    <flux:table.column>{{ __('Prioridad') }}</flux:table.column>
                    <flux:table.column class="hidden lg:table-cell">{{ __('Urgencia') }}</flux:table.column>
                    <flux:table.column class="hidden lg:table-cell">{{ __('Impacto') }}</flux:table.column>
                    <flux:table.column class="hidden lg:table-cell">{{ __('Creado') }}</flux:table.column>
                    <flux:table.column>{{ __('Acciones') }}</flux:table.column>
                </flux:table.row>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($tickets as $ticket)
                    <flux:table.row :key="$ticket->id">
                        <flux:table.cell>{{ $ticket->id }}</flux:table.cell>
                        <flux:table.cell class="hidden lg:table-cell">{{ $ticket->user->name }}</flux:table.cell>
                        <flux:table.cell class="whitespace-normal">
                            <a href="{{ route('ticket.show', $ticket) }}" wire:navigate class="block min-w-40 wrap-break-word hover:underline">
                                {{ $ticket->title }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $ticket->priority }}</flux:table.cell>
                        <flux:table.cell class="hidden lg:table-cell">{{ $ticket->urgency }}</flux:table.cell>
                        <flux:table.cell class="hidden lg:table-cell">{{ $ticket->impact }}</flux:table.cell>
                        <flux:table.cell class="hidden lg:table-cell">{{ $ticket->created_at->diffForHumans() }}</flux:table.cell>
                        <flux:table.cell class="flex flex-wrap items-center gap-2">
                            @if ($rejectingTicketId === $ticket->id)
                                <form wire:submit="reject" class="flex flex-wrap items-center gap-2">
                                    <flux:input size="sm" wire:model="rejectionReason" placeholder="{{ __('Motivo del rechazo') }}" />
                                    <flux:button size="sm" variant="danger" type="submit">{{ __('Confirmar rechazo') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="cancelRejecting">{{ __('Cancelar') }}</flux:button>
                                </form>
                            @else
                                <flux:modal.trigger name="approve-ticket-triage-{{ $ticket->id }}">
                                    <flux:button size="sm" variant="primary">
                                        {{ __('Aprobar') }}
                                    </flux:button>
                                </flux:modal.trigger>
                                <flux:button size="sm" variant="danger" wire:click="startRejecting({{ $ticket->id }})">
                                    {{ __('Rechazar') }}
                                </flux:button>

                                <flux:modal name="approve-ticket-triage-{{ $ticket->id }}" class="max-w-lg">
                                    <div class="space-y-6">
                                        <div>
                                            <flux:heading size="lg">{{ __('Aprobar ticket') }}</flux:heading>
                                            <flux:subheading>{{ __('El ticket saldrá de triage y quedará disponible para asignar.') }}</flux:subheading>
                                        </div>

                                        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                                            <flux:modal.close>
                                                <flux:button variant="filled">{{ __('Cancelar') }}</flux:button>
                                            </flux:modal.close>

                                            <flux:button variant="primary" wire:click="approve({{ $ticket->id }})" wire:loading.attr="disabled" wire:target="approve({{ $ticket->id }})">
                                                {{ __('Confirmar aprobación') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                </flux:modal>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
