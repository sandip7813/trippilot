<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('guests cannot upload a profile picture', function () {
    $this->post(route('profile-picture.update'), [
        'avatar' => UploadedFile::fake()->image('me.jpg'),
    ])->assertRedirect(route('login'));
});

test('users can upload a profile picture that is cropped to a square', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile-picture.update'), [
            'avatar' => UploadedFile::fake()->image('me.png', 1200, 800),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile-picture.edit'));

    $path = $user->refresh()->avatar_path;

    expect($path)->toStartWith("avatars/{$user->id}/")->toEndWith('.jpg');
    Storage::disk('public')->assertExists($path);

    $dimensions = getimagesizefromstring(Storage::disk('public')->get($path));

    expect([$dimensions[0], $dimensions[1]])->toBe([400, 400])
        ->and($user->avatar)->toBe(Storage::disk('public')->url($path));
});

test('uploading a new profile picture deletes the previous file', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('profile-picture.update'), [
        'avatar' => UploadedFile::fake()->image('first.jpg'),
    ]);
    $firstPath = $user->refresh()->avatar_path;

    $this->actingAs($user)->post(route('profile-picture.update'), [
        'avatar' => UploadedFile::fake()->image('second.jpg'),
    ]);
    $secondPath = $user->refresh()->avatar_path;

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);
});

test('a non-image file is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile-picture.update'), [
            'avatar' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('avatar');

    expect($user->refresh()->avatar_path)->toBeNull();
});

test('a profile picture larger than 5 MB is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile-picture.update'), [
            'avatar' => UploadedFile::fake()->image('huge.jpg')->size(5121),
        ])
        ->assertSessionHasErrors(['avatar' => 'The profile picture must not be larger than 5 MB.']);
});

test('users can remove their profile picture', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('profile-picture.update'), [
        'avatar' => UploadedFile::fake()->image('me.jpg'),
    ]);
    $path = $user->refresh()->avatar_path;

    $this->actingAs($user)
        ->delete(route('profile-picture.destroy'))
        ->assertRedirect(route('profile-picture.edit'));

    expect($user->refresh()->avatar_path)->toBeNull()
        ->and($user->avatar)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('the shared auth user exposes the avatar url but not the storage path', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('profile-picture.update'), [
        'avatar' => UploadedFile::fake()->image('me.jpg'),
    ]);
    $user->refresh();

    $this->actingAs($user)
        ->get(route('profile-picture.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('settings/ProfilePicture')
            ->where('auth.user.avatar', $user->avatar)
            ->missing('auth.user.avatar_path')
        );
});

test('deleting a user removes their profile picture file', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('profile-picture.update'), [
        'avatar' => UploadedFile::fake()->image('me.jpg'),
    ]);
    $path = $user->refresh()->avatar_path;

    $user->delete();

    Storage::disk('public')->assertMissing($path);
});
