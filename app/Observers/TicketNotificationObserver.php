<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Notifications\TicketAwaitingResponse;
use App\Notifications\TicketResolved;
use App\Notifications\TicketTriageRejected;
use Illuminate\Notifications\Notification;

/**
 * Mails the ticket's author when the team needs something from them.
 *
 * Kept apart from TicketObserver, which only records the history. Every path
 * that changes these fields goes through Eloquent's update, so watching here
 * covers the status selector and both triage rejections without each of them
 * having to remember to notify.
 */
class TicketNotificationObserver
{
    /**
     * Handle the Ticket "updated" event.
     */
    public function updated(Ticket $ticket): void
    {
        $notification = $this->notificationFor($ticket);

        if ($notification === null) {
            return;
        }

        $author = $ticket->user;

        if ($author === null || $author->id === auth()->id()) {
            return;
        }

        $author->notify($notification);
    }

    private function notificationFor(Ticket $ticket): ?Notification
    {
        if ($ticket->wasChanged('status') && $ticket->isAwaitingResponse()) {
            return new TicketAwaitingResponse($ticket);
        }

        if ($ticket->wasChanged('status') && $ticket->status === 'resolved') {
            return new TicketResolved($ticket);
        }

        if ($ticket->wasChanged('triage_status') && $ticket->isTriageRejected()) {
            return new TicketTriageRejected($ticket, $this->rejectionReason($ticket));
        }

        return null;
    }

    /**
     * Both rejection paths write the reason as a chat message right before
     * flipping triage_status, so at this point it is the ticket's latest one.
     */
    private function rejectionReason(Ticket $ticket): ?string
    {
        return $ticket->messages()->reorder()->latest('id')->value('body');
    }
}
