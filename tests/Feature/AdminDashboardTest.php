<?php

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

test('admin dashboard shows platform stats', function () {
    skipUnlessMongoDbAvailable();

    Cache::forget('admin:dashboard:stats');
    Trip::query()->whereNotNull('_id')->delete();

    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();
    User::factory()->superAdmin()->create();

    $owner = User::factory()->create();

    Trip::factory()->forUser($owner)->count(2)->create();
    Trip::factory()->forUser($owner)->road()->create();
    Trip::factory()->forUser($owner)->archived()->create();

    Trip::factory()->forUser($owner)->withItinerary()->create([
        'chat_messages' => [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there'],
            ['role' => 'assistant', 'content' => 'Here is an idea'],
        ],
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard')
            ->where('stats.users.total', 5)
            ->where('stats.users.admins', 2)
            ->where('stats.trips.total', 4)
            ->where('stats.trips.vacation', 3)
            ->where('stats.trips.road', 1)
            ->where('stats.ai_requests.chat_replies', 2)
            ->where('stats.ai_requests.itineraries', 1)
            ->where('stats.ai_requests.total', 3));
});

test('regular users cannot access admin dashboard stats', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('admin.dashboard'))->assertForbidden();
});
