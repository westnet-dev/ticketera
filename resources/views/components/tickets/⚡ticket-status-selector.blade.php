<?php

use App\Models\Ticket;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    public Ticket $ticket;

    public function updateStatus(string $status): void
    {
        Gate::authorize('changeStatus', $this->ticket);

        if ($status === 'draft' || ! in_array($status, Ticket::STATUSES, true)) {
            return;
        }

        $this->ticket->update(['status' => $status]);
    }
};
?>

<div>
    <flux:select size="sm" wire:change="updateStatus($event.target.value)">
        @foreach (array_diff(Ticket::STATUSES, ['draft']) as $status)
            <flux:select.option value="{{ $status }}" :selected="$ticket->status === $status">
                {{ Ticket::labelForStatus($status) }}
            </flux:select.option>
        @endforeach
    </flux:select>
</div>
