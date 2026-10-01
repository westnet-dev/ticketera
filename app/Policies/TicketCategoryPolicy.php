<?php

namespace App\Policies;

use App\Models\TicketCategory;
use App\Models\User;

class TicketCategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TicketCategory $ticketCategory): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Blocks deleting a category that is still assigned to tickets, so no
     * ticket silently loses its classification.
     */
    public function delete(User $user, TicketCategory $ticketCategory): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        return $ticketCategory->tickets()->doesntExist();
    }
}
