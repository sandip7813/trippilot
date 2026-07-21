<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function updateRole(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false;
        }

        if ($target->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        return $user->isAdmin();
    }
}
