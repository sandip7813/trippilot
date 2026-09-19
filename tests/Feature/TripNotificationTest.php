<?php

use App\Enums\TripCollaboratorRole;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\TripReminderNotification;
use App\Notifications\TripSharedNotification;
use Illuminate\Support\Facades\Notification;

test('reminders are sent to the owner once per reminder window', function () {
    skipUnlessMongoDbAvailable();
    Notification::fake();
    Trip::query()->whereNotNull('_id')->delete();

    $owner = User::factory()->create();
    $collaborator = User::factory()->create();

    $trip = Trip::factory()->forUser($owner)->planned()->create([
        'start_date' => now()->addDays(7)->toDateString(),
        'collaborators' => [[
            'user_id' => $collaborator->id,
            'email' => $collaborator->email,
            'role' => 'viewer',
            'status' => 'accepted',
            'added_at' => now()->toIso8601String(),
        ]],
    ]);
    Trip::factory()->forUser($owner)->planned()->create([
        'start_date' => now()->addDays(30)->toDateString(),
    ]);
    Trip::factory()->forUser($owner)->archived()->create([
        'start_date' => now()->addDays(3)->toDateString(),
    ]);

    $this->artisan('trippilot:send-trip-reminders')->assertSuccessful();

    Notification::assertSentTo([$owner, $collaborator], TripReminderNotification::class, fn ($n) => $n->tripId === $trip->id && $n->daysUntil === 7);
    Notification::assertCount(2);
    expect($trip->fresh()->reminders_sent)->toBe([7]);

    $this->artisan('trippilot:send-trip-reminders')->assertSuccessful();
    Notification::assertCount(2);
});

test('users can view and mark notifications as read', function () {
    $user = User::factory()->create();
    $user->notify(new TripSharedNotification('abc123', 'Goa', TripCollaboratorRole::Viewer));

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Notifications/Index')
            ->has('items.data', 1)
            ->where('items.data.0.read', false)
            ->where('items.data.0.url', '/trips/abc123')
            ->where('notifications.unread', 1));

    $notification = $user->notifications()->first();

    $this->patch(route('notifications.read', $notification->id))
        ->assertRedirect('/trips/abc123');

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('users can mark all notifications as read', function () {
    $user = User::factory()->create();
    $user->notify(new TripSharedNotification('a', 'A', TripCollaboratorRole::Viewer));
    $user->notify(new TripSharedNotification('b', 'B', TripCollaboratorRole::Viewer));

    $this->actingAs($user)->patch(route('notifications.read-all'))->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('users cannot read another users notifications', function () {
    $owner = User::factory()->create();
    $owner->notify(new TripSharedNotification('a', 'A', TripCollaboratorRole::Viewer));

    $this->actingAs(User::factory()->create())
        ->patch(route('notifications.read', $owner->notifications()->first()->id))
        ->assertNotFound();
});

test('unread count is shared with inertia pages', function () {
    $user = User::factory()->create();
    $user->notify(new TripSharedNotification('a', 'A', TripCollaboratorRole::Viewer));

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertInertia(fn ($page) => $page->where('notifications.unread', 1));
});

test('the latest six notifications are shared for the header dropdown', function () {
    $user = User::factory()->create();

    foreach (range(1, 8) as $number) {
        $user->notify(new TripSharedNotification("trip-{$number}", "Trip {$number}", TripCollaboratorRole::Viewer));
    }

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertInertia(fn ($page) => $page
            ->where('notifications.unread', 8)
            ->has('notifications.recent', 6)
            ->has('items.data', 8));
});

test('a notification can be marked read without following its link', function () {
    $user = User::factory()->create();
    $user->notify(new TripSharedNotification('abc', 'Goa', TripCollaboratorRole::Viewer));

    $this->actingAs($user)
        ->from(route('notifications.index'))
        ->patch(route('notifications.read', ['notification' => $user->notifications()->first()->id, 'stay' => 1]))
        ->assertRedirect(route('notifications.index'));

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('notifications are paginated and can be filtered to unread', function () {
    $user = User::factory()->create();

    foreach (range(1, 12) as $number) {
        $user->notify(new TripSharedNotification("trip-{$number}", "Trip {$number}", TripCollaboratorRole::Viewer));
    }

    $user->notifications()->limit(5)->get()->each->markAsRead();

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertInertia(fn ($page) => $page
            ->has('items.data', 10)
            ->where('items.last_page', 2)
            ->where('items.total', 12)
            ->where('counts.all', 12)
            ->where('counts.unread', 7)
            ->where('filter', 'all'));

    $this->get(route('notifications.index', ['filter' => 'unread']))
        ->assertInertia(fn ($page) => $page
            ->has('items.data', 7)
            ->where('items.last_page', 1)
            ->where('filter', 'unread'));
});
