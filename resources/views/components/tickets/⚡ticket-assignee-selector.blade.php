<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    public Ticket $ticket;

    /**
     * An empty value unassigns the ticket; anyone who is not an active admin is ignored.
     */
    public function assign(string $userId): void
    {
        Gate::authorize('assign', $this->ticket);

        $assignee = $userId === '' ? null : User::assignable()->find((int) $userId);

        if ($userId !== '' && $assignee === null) {
            return;
        }

        if ($assignee?->id === $this->ticket->assigned_to) {
            return;
        }

        DB::transaction(fn () => $this->ticket->assignTo($assignee));

        $this->dispatch('ticket-assignee-changed');
    }

    public function with(): array
    {
        $assignableUsers = User::assignable()->orderBy('name')->get();

        return [
            'assignableUsers' => $assignableUsers,
            // A deleted admin can still hold the ticket. They are shown so the
            // select does not read as "Sin asignar", but cannot be picked again.
            'formerAssignee' => $this->ticket->assigned_to !== null && ! $assignableUsers->contains('id', $this->ticket->assigned_to)
                ? User::withTrashed()->find($this->ticket->assigned_to)
                : null,
        ];
    }
};
?>

<div>
    <flux:select size="sm" wire:change="assign($event.target.value)">
        <flux:select.option value="" :selected="$ticket->assigned_to === null">
            {{ __('Sin asignar') }}
        </flux:select.option>
        @if ($formerAssignee !== null)
            <flux:select.option value="{{ $formerAssignee->id }}" selected disabled>
                {{ $formerAssignee->name }}
            </flux:select.option>
        @endif
        @foreach ($assignableUsers as $assignable)
            <flux:select.option value="{{ $assignable->id }}" :selected="$ticket->assigned_to === $assignable->id">
                {{ $assignable->name }}
            </flux:select.option>
        @endforeach
    </flux:select>
</div>
