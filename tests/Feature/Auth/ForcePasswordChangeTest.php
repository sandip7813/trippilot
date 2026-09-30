<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('users logging in with a one-time password are redirected to change it', function () {
    $user = User::factory()->unverified()->create([
        'password' => 'one-time-pass',
        'must_change_password' => true,
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'one-time-pass',
    ]);

    $this->assertAuthenticatedAs($user);

    $this->get(route('dashboard'))->assertRedirect(route('password.change'));
    $this->get(route('profile.edit'))->assertRedirect(route('password.change'));
});

test('change password page can be rendered for users with a one-time password', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)
        ->get(route('password.change'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/ChangePassword'));
});

test('users without a one-time password are sent away from the change password page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('password.change'))
        ->assertRedirect(route('dashboard'));
});

test('users can replace their one-time password and continue', function () {
    $user = User::factory()->unverified()->create([
        'password' => 'one-time-pass',
        'must_change_password' => true,
    ]);

    $response = $this->actingAs($user)->put(route('password.change.update'), [
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('new-secure-password', $user->password))->toBeTrue();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

test('new password must differ from the one-time password', function () {
    $user = User::factory()->create([
        'password' => 'one-time-pass',
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->put(route('password.change.update'), [
            'password' => 'one-time-pass',
            'password_confirmation' => 'one-time-pass',
        ])
        ->assertSessionHasErrors('password');

    expect($user->refresh()->must_change_password)->toBeTrue();
});

test('new password must be confirmed', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)
        ->put(route('password.change.update'), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'something-else',
        ])
        ->assertSessionHasErrors('password');
});

test('users with a one-time password can still log out', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
});
