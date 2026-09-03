<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
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
    public function view(User $user, User $model): bool
    {
        return false;
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
    public function update(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can change the target user's role.
     *
     * Blocks an admin from removing their own admin role, and blocks
     * demoting the last remaining admin account.
     */
    public function updateRole(User $user, User $model, Role $newRole): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        if ($newRole === Role::Admin) {
            return true;
        }

        if ($user->is($model)) {
            return false;
        }

        if ($model->isAdmin() && User::activeAdminCount() <= 1) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can change the target user's area.
     */
    public function updateArea(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can force a password reset for the target user.
     */
    public function resetPassword(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        if ($user->is($model)) {
            return false;
        }

        if ($model->isAdmin() && User::activeAdminCount() <= 1) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
