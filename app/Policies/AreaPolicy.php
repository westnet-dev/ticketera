<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;

class AreaPolicy
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
    public function update(User $user, Area $area): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Blocks deleting an area that still has users assigned to it, to
     * preserve historical traceability for future metrics.
     */
    public function delete(User $user, Area $area): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        return ! $area->users()->exists();
    }
}
