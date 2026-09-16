<?php

use App\Models\User;

test('name accessor combines the first and last name', function () {
    $user = User::factory()->make([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);

    expect($user->name)->toBe('Ada Lovelace');
});

test('name is included when the user is serialized for the frontend', function () {
    $user = User::factory()->make([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);

    expect($user->toArray())->toHaveKey('name', 'Ada Lovelace');
});
