<?php

use App\Actions\Admin\UpdateUserRole;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('admins can list users', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/Index')
            ->has('users.data', 3)
            ->has('role_options', 3));
});

test('regular users cannot access user management', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();

    $target = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('admin.users.update', $target), ['role' => 'admin'])
        ->assertForbidden();
});

test('super admin can promote and demote user roles', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $target = User::factory()->create();

    $this->actingAs($superAdmin)
        ->patch(route('admin.users.update', $target), ['role' => 'admin'])
        ->assertRedirect();

    expect($target->refresh()->role)->toBe(UserRole::Admin);

    $this->actingAs($superAdmin)
        ->patch(route('admin.users.update', $target), ['role' => 'super_admin'])
        ->assertRedirect();

    expect($target->refresh()->role)->toBe(UserRole::SuperAdmin);

    $this->actingAs($superAdmin)
        ->patch(route('admin.users.update', $target), ['role' => 'user'])
        ->assertRedirect();

    expect($target->refresh()->role)->toBe(UserRole::User);
});

test('admin can manage regular users and other admins but not super admins', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();
    $otherAdmin = User::factory()->admin()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $target), ['role' => 'admin'])
        ->assertRedirect();

    expect($target->refresh()->role)->toBe(UserRole::Admin);

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $otherAdmin), ['role' => 'user'])
        ->assertRedirect();

    expect($otherAdmin->refresh()->role)->toBe(UserRole::User);

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $superAdmin), ['role' => 'admin'])
        ->assertForbidden();

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $target), ['role' => 'super_admin'])
        ->assertSessionHasErrors('role');
});

test('users cannot change their own role', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->patch(route('admin.users.update', $superAdmin), ['role' => 'user'])
        ->assertForbidden();
});

test('cannot demote the last super admin', function () {
    $target = User::factory()->superAdmin()->create();
    $actor = User::factory()->superAdmin()->create();

    User::query()->whereKey($actor->id)->update(['role' => UserRole::Admin]);

    expect(fn () => app(UpdateUserRole::class)(
        $actor,
        $target,
        UserRole::Admin,
    ))->toThrow(ValidationException::class);
});

test('user index marks self and assignable roles correctly', function () {
    $admin = User::factory()->admin()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data', function ($users) use ($admin, $superAdmin): bool {
                $self = collect($users)->firstWhere('id', $admin->id);
                $protected = collect($users)->firstWhere('id', $superAdmin->id);

                return $self['is_self'] === true
                    && $self['can_update_role'] === false
                    && $protected['can_update_role'] === false
                    && $protected['role'] === 'super_admin';
            }));
});
