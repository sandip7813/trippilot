<?php

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateUserRole
{
    public function __invoke(User $actor, User $target, UserRole $role): User
    {
        if ($actor->cannot('updateRole', $target)) {
            abort(403);
        }

        $assignableRoles = UserRole::assignableBy($actor->role, $target->role);

        if (! in_array($role, $assignableRoles, true)) {
            throw ValidationException::withMessages([
                'role' => __('You cannot assign that role.'),
            ]);
        }

        if ($target->isSuperAdmin() && $role !== UserRole::SuperAdmin) {
            $remainingSuperAdmins = User::query()
                ->where('role', UserRole::SuperAdmin)
                ->whereKeyNot($target->id)
                ->count();

            if ($remainingSuperAdmins === 0) {
                throw ValidationException::withMessages([
                    'role' => __('At least one super admin must remain on the platform.'),
                ]);
            }
        }

        $target->update(['role' => $role]);

        return $target->refresh();
    }
}
