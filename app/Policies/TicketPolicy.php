<?php

namespace App\Policies;

use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\TicketSetting;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->id === $ticket->user_id || $user->isAdmin();
    }

    /**
     * Determine whether the user can assign the model to an admin user.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() && $ticket->triage_status === TriageStatus::Approved;
    }

    /**
     * Determine whether the user can change the ticket's status.
     */
    public function changeStatus(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() && $ticket->triage_status === TriageStatus::Approved;
    }

    /**
     * Determine whether the user can approve the ticket out of triage.
     */
    public function approve(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() && $ticket->triage_status === TriageStatus::Pending;
    }

    /**
     * Determine whether the user can reject the ticket out of triage.
     */
    public function reject(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() && $ticket->triage_status === TriageStatus::Pending;
    }

    /**
     * Determine whether the user can revise and resubmit a rejected ticket.
     */
    public function reviseTriage(User $user, Ticket $ticket): bool
    {
        return $user->id === $ticket->user_id && $ticket->triage_status === TriageStatus::Rejected;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->tickets()->where('status', '!=', 'closed')->count() < TicketSetting::current()->max_open_tickets_per_user;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Ticket $ticket): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Ticket $ticket): bool
    {
        return false;
    }
}
