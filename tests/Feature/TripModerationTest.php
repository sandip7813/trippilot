<?php

use App\Enums\TripReportStatus;
use App\Models\Trip;
use App\Models\TripReport;
use App\Models\User;

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
    TripReport::query()->whereNotNull('_id')->delete();
});

test('a logged in user can report a public trip', function () {
    $owner = User::factory()->create();
    $reporter = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $this->actingAs($reporter)->post(route('trips.report', $trip), [
        'reason' => 'scam',
        'message' => 'Looks suspicious.',
    ])->assertRedirect();

    expect(TripReport::query()->where('trip_id', (string) $trip->id)->count())->toBe(1);
});

test('a guest cannot report a trip', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $this->post(route('trips.report', $trip), ['reason' => 'spam'])->assertRedirect(route('login'));
});

test('an owner cannot report their own trip', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $this->actingAs($owner)->post(route('trips.report', $trip), ['reason' => 'spam'])->assertForbidden();
});

test('a private trip cannot be reported', function () {
    $owner = User::factory()->create();
    $reporter = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($reporter)->post(route('trips.report', $trip), ['reason' => 'spam'])->assertForbidden();
});

test('a non admin cannot view the moderation queue', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.trip-reports.index'))->assertForbidden();
});

test('an admin can view the queue and take a trip down', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();
    $report = TripReport::factory()->forTrip($trip)->create();

    $this->actingAs($admin)->get(route('admin.trip-reports.index'))
        ->assertInertia(fn ($page) => $page->has('reports', 1));

    $this->actingAs($admin)->post(route('admin.trip-reports.takedown', $report))->assertRedirect();

    expect($trip->fresh()->isPublic())->toBeFalse()
        ->and($report->fresh()->status)->toBe(TripReportStatus::ActionTaken);
});

test('an admin can dismiss a report without taking the trip down', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();
    $report = TripReport::factory()->forTrip($trip)->create();

    $this->actingAs($admin)->post(route('admin.trip-reports.dismiss', $report))->assertRedirect();

    expect($trip->fresh()->isPublic())->toBeTrue()
        ->and($report->fresh()->status)->toBe(TripReportStatus::Reviewed);
});
