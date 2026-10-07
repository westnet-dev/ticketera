<?php

namespace App\Policies;

use App\Enums\TriageStatus;
use App\Enums\ValidationStatus;
use App\Models\Area;
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
     *
     * Members of the area a ticket was filed for can read it, since they share
     * its ticket cap. Reading is all they get: every action on the ticket keeps
     * checking for its author. Drafts stay private to their author.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if ($ticket->isDraft()) {
            return $user->id === $ticket->user_id;
        }

        return $user->id === $ticket->user_id
            || $user->isAdmin()
            || $user->belongsToArea($ticket->area_id);
    }

    /**
     * Determine whether the user can post in the ticket's chat.
     *
     * Teammates who can read the ticket through its area can also follow up on
     * it and give feedback, alongside its author and the team.
     */
    public function reply(User $user, Ticket $ticket): bool
    {
        return $ticket->isRequestedBy($user) || $user->isAdmin();
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
     * Determine whether the user can estimate the ticket's difficulty.
     *
     * Not tied to triage approval: estimating effort is most useful while triaging.
     */
    public function estimate(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
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
     * Determine whether the user can validate the ticket's resolution.
     *
     * The requesting side validates: the author or any member of the ticket's
     * area, since they share the need it covers. Never an admin, not even one
     * who filed it on a client's behalf: that would be signing off on their own
     * team's work.
     */
    public function validateResolution(User $user, Ticket $ticket): bool
    {
        return $ticket->isRequestedBy($user) && $ticket->validation_status === ValidationStatus::Pending;
    }

    /**
     * Determine whether the user can create models.
     *
     * The cap is evaluated against the area the ticket is filed for, so it stays
     * finite no matter how many people the area has. A ticket without an area
     * falls back to the user's own count against that same number.
     */
    public function create(User $user, ?Area $area = null): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->openTicketCountForLimit($area) < TicketSetting::current()->max_open_tickets_per_area;
    }

    /**
     * Determine whether the user can create a ticket authored by someone else.
     */
    public function createForOthers(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $ticket->isDraft() && $user->id === $ticket->user_id;
    }

    /**
     * Determine whether the user can edit a submitted ticket's details.
     *
     * Admins can edit any submitted ticket. Its author can only edit it while it
     * waits on triage: once approved, its information is frozen for them, and a
     * rejected ticket goes through reviseTriage instead. Drafts have their own flow.
     */
    public function edit(User $user, Ticket $ticket): bool
    {
        if ($ticket->isDraft()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->id === $ticket->user_id && $ticket->triage_status === TriageStatus::Pending;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $ticket->isDraft() && $user->id === $ticket->user_id;
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
