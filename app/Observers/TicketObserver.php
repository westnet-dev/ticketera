<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Models\TicketHistory;
use BackedEnum;

class TicketObserver
{
    /**
     * Fields whose changes are recorded in the ticket history.
     *
     * @var array<int, string>
     */
    private const WATCHED_FIELDS = ['status', 'triage_status', 'assigned_to'];

    /**
     * Handle the Ticket "updated" event.
     */
    public function updated(Ticket $ticket): void
    {
        foreach (self::WATCHED_FIELDS as $field) {
            if (! $ticket->wasChanged($field)) {
                continue;
            }

            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
                'field' => $field,
                'from_value' => $this->stringify($ticket->getOriginal($field)),
                'to_value' => $this->stringify($ticket->getAttribute($field)),
            ]);
        }
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }
}
