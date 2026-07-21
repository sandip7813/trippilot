<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\UpdateUserRole;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();

        $users = User::query()
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user): array => $this->userPayload($actor, $user));

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'role_options' => $this->roleOptions(),
        ]);
    }

    public function update(
        UpdateUserRoleRequest $request,
        User $user,
        UpdateUserRole $updateUserRole,
    ): RedirectResponse {
        $updateUserRole(
            $request->user(),
            $user,
            UserRole::from($request->validated('role')),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User role updated.'),
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $actor, User $user): array
    {
        $assignableRoles = $actor->can('updateRole', $user)
            ? UserRole::assignableBy($actor->role, $user->role)
            : [];

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'can_update_role' => $assignableRoles !== [],
            'assignable_roles' => array_map(
                fn (UserRole $role): array => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ],
                $assignableRoles,
            ),
            'is_self' => $actor->id === $user->id,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        return array_map(
            fn (UserRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ],
            UserRole::cases(),
        );
    }
}
