<?php

use App\Enums\ValidationStatus;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
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

        if ($status === $this->ticket->status) {
            return;
        }

        DB::transaction(fn () => $this->ticket->update([
            'status' => $status,
            ...$this->validationAttributesFor($status),
        ]));
    }

    /**
     * Resolving a ticket asks its author to validate the outcome; taking it back
     * out of `resolved` before they answer withdraws that request.
     *
     * @return array<string, mixed>
     */
    private function validationAttributesFor(string $status): array
    {
        if ($status === 'resolved') {
            return [
                'validation_status' => ValidationStatus::Pending,
                'resolution_rating' => null,
                'validated_at' => null,
            ];
        }

        if ($this->ticket->status === 'resolved' && $this->ticket->awaitsValidation()) {
            return ['validation_status' => ValidationStatus::NotRequested];
        }

        return [];
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
