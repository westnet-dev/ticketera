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
                    <flux:table.column>{{ __('Cliente') }}</flux:table.column>
                    <flux:table.column>{{ __('Título') }}</flux:table.column>
                    <flux:table.column>{{ __('Prioridad') }}</flux:table.column>
                    <flux:table.column>{{ __('Urgencia') }}</flux:table.column>
                    <flux:table.column>{{ __('Impacto') }}</flux:table.column>
                    <flux:table.column>{{ __('Creado') }}</flux:table.column>
                    <flux:table.column>{{ __('Acciones') }}</flux:table.column>
                </flux:table.row>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($tickets as $ticket)
                    <flux:table.row :key="$ticket->id">
                        <flux:table.cell>{{ $ticket->id }}</flux:table.cell>
                        <flux:table.cell>{{ $ticket->user->name }}</flux:table.cell>
                        <flux:table.cell>
                            <a href="{{ route('ticket.show', $ticket) }}" wire:navigate class="hover:underline">
                                {{ $ticket->title }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $ticket->priority }}</flux:table.cell>
                        <flux:table.cell>{{ $ticket->urgency }}</flux:table.cell>
                        <flux:table.cell>{{ $ticket->impact }}</flux:table.cell>
                        <flux:table.cell>{{ $ticket->created_at->diffForHumans() }}</flux:table.cell>
                        <flux:table.cell class="flex items-center gap-2">
                            @if ($rejectingTicketId === $ticket->id)
                                <form wire:submit="reject" class="flex items-center gap-2">
                                    <flux:input size="sm" wire:model="rejectionReason" placeholder="{{ __('Motivo del rechazo') }}" />
                                    <flux:button size="sm" variant="danger" type="submit">{{ __('Confirmar rechazo') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="cancelRejecting">{{ __('Cancelar') }}</flux:button>
                                </form>
                            @else
                                <flux:button size="sm" variant="primary" wire:click="approve({{ $ticket->id }})">
                                    {{ __('Aprobar') }}
                                </flux:button>
                                <flux:button size="sm" variant="danger" wire:click="startRejecting({{ $ticket->id }})">
                                    {{ __('Rechazar') }}
                                </flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
