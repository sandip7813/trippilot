<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'home_city' => [
                'label' => 'Bangalore, India',
                'lat' => 12.9716,
                'lng' => 77.5946,
                'place_id' => 'place-blr',
                'country_code' => 'in',
            ],
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
    expect($user->travel_preferences['home_city'])->toMatchArray([
        'label' => 'Bangalore, India',
        'lat' => 12.9716,
        'lng' => 77.5946,
        'place_id' => 'place-blr',
        'country_code' => 'in',
    ]);
});

test('profile email must have a proper domain', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'dfsdfds@asd',
        ])
        ->assertSessionHasErrors('email');

    expect($user->refresh()->email)->not->toBe('dfsdfds@asd');
});

test('profile mobile number is saved with its country code', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'mobile_country_code' => '+971',
            'mobile_number' => '501234567',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh())
        ->mobile_country_code->toBe('+971')
        ->mobile_number->toBe('501234567');
});

test('profile mobile number can be removed', function () {
    $user = User::factory()->create(['mobile_country_code' => '+91', 'mobile_number' => '9876543210']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'mobile_country_code' => '+91',
            'mobile_number' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh())
        ->mobile_country_code->toBeNull()
        ->mobile_number->toBeNull();
});

test('profile rejects a mobile number of the wrong length for its country', function () {
    $user = User::factory()->create(['mobile_country_code' => '+91', 'mobile_number' => '9876543210']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'mobile_country_code' => '+91',
            'mobile_number' => '98765',
        ])
        ->assertSessionHasErrors('mobile_number');

    expect($user->refresh()->mobile_number)->toBe('9876543210');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
