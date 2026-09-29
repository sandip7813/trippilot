<?php

use App\Enums\TripCollaboratorRole;
use App\Enums\TripJoinRequestStatus;
use App\Models\Trip;
use App\Models\TripExpenseSheet;
use App\Models\TripJoinRequest;
use App\Models\User;

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
    TripJoinRequest::query()->whereNotNull('_id')->delete();
    TripExpenseSheet::query()->whereNotNull('_id')->delete();
});

test('a logged in user can request to join an open trip', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $this->actingAs($requester)->post(route('trips.join-requests.store', $trip), [
        'travelers_count' => 2,
        'phone' => '9876543210',
        'message' => 'Excited to join!',
        'accepts_terms' => true,
    ])->assertRedirect();

    $request = TripJoinRequest::query()->where('trip_id', (string) $trip->id)->first();

    expect($request)->not->toBeNull()
        ->and($request->status)->toBe(TripJoinRequestStatus::Pending)
        ->and($request->travelers_count)->toBe(2);
});

test('a guest cannot request to join', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $this->post(route('trips.join-requests.store', $trip), [
        'travelers_count' => 1,
        'phone' => '9876543210',
        'accepts_terms' => true,
    ])->assertRedirect(route('login'));
});

test('the owner cannot request to join their own trip', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $this->actingAs($owner)->post(route('trips.join-requests.store', $trip), [
        'travelers_count' => 1,
        'phone' => '9876543210',
        'accepts_terms' => true,
    ])->assertForbidden();
});

test('joining is blocked once the trip is past, full, or private', function () {
    $requester = User::factory()->create();
    $owner = User::factory()->create();

    $past = Trip::factory()->forUser($owner)->openTrip()->create(['end_date' => now()->subDay()]);
    $this->actingAs($requester)->post(route('trips.join-requests.store', $past), [
        'travelers_count' => 1, 'phone' => '9876543210', 'accepts_terms' => true,
    ])->assertForbidden();

    $private = Trip::factory()->forUser($owner)->create();
    $this->actingAs($requester)->post(route('trips.join-requests.store', $private), [
        'travelers_count' => 1, 'phone' => '9876543210', 'accepts_terms' => true,
    ])->assertForbidden();

    $full = Trip::factory()->forUser($owner)->openTrip()->create();
    $full->update(['open_trip' => array_merge($full->openTripDetails(), ['max_group_size' => 0])]);
    $this->actingAs($requester)->post(route('trips.join-requests.store', $full), [
        'travelers_count' => 1, 'phone' => '9876543210', 'accepts_terms' => true,
    ])->assertForbidden();
});

test('a user cannot submit a second pending request for the same trip', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $this->actingAs($requester)->post(route('trips.join-requests.store', $trip), [
        'travelers_count' => 1, 'phone' => '9876543210', 'accepts_terms' => true,
    ]);

    $this->actingAs($requester)->post(route('trips.join-requests.store', $trip), [
        'travelers_count' => 1, 'phone' => '9876543210', 'accepts_terms' => true,
    ])->assertSessionHasErrors('join');

    expect(TripJoinRequest::query()->where('trip_id', (string) $trip->id)->count())->toBe(1);
});

test('a requester can withdraw their own pending request', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();
    $request = TripJoinRequest::factory()->forTrip($trip)->forUser($requester)->create();

    $this->actingAs($requester)->delete(route('trips.join-requests.destroy', [$trip, $request]))->assertRedirect();

    expect($request->fresh()->status)->toBe(TripJoinRequestStatus::Withdrawn);
});

test('a stranger cannot withdraw someone elses request', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $stranger = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();
    $request = TripJoinRequest::factory()->forTrip($trip)->forUser($requester)->create();

    $this->actingAs($stranger)->delete(route('trips.join-requests.destroy', [$trip, $request]))
        ->assertSessionHasErrors('join');

    expect($request->fresh()->status)->toBe(TripJoinRequestStatus::Pending);
});

test('the owner can accept a join request, which adds the requester as a member', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();
    $request = TripJoinRequest::factory()->forTrip($trip)->forUser($requester)->create();

    $this->actingAs($owner)->post(route('trips.join-requests.accept', [$trip, $request]))->assertRedirect();

    expect($request->fresh()->status)->toBe(TripJoinRequestStatus::Accepted)
        ->and($trip->fresh()->isMember($requester))->toBeTrue();
});

test('accepting past the seat limit fails', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();
    $trip->update(['open_trip' => array_merge($trip->openTripDetails(), ['max_group_size' => 0])]);
    $request = TripJoinRequest::factory()->forTrip($trip)->forUser($requester)->create();

    $this->actingAs($owner)->post(route('trips.join-requests.accept', [$trip, $request]))
        ->assertSessionHasErrors('join');

    expect($request->fresh()->status)->toBe(TripJoinRequestStatus::Pending)
        ->and($trip->fresh()->isMember($requester))->toBeFalse();
});

test('accepting adds the requester as an expense participant when the sheet is shared', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->expenseSheetShared()->create();
    TripExpenseSheet::factory()->forTrip($trip)->create();
    $request = TripJoinRequest::factory()->forTrip($trip)->forUser($requester)->create(['phone' => '9998887776']);

    $this->actingAs($owner)->post(route('trips.join-requests.accept', [$trip, $request]));

    $sheet = $trip->fresh()->expenseSheet();

    expect(collect($sheet->participantList())->pluck('email'))->toContain(strtolower($requester->email));
});

test('accepting does not touch the expense sheet when it is private', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();
    TripExpenseSheet::factory()->forTrip($trip)->create();
    $request = TripJoinRequest::factory()->forTrip($trip)->forUser($requester)->create();

    $this->actingAs($owner)->post(route('trips.join-requests.accept', [$trip, $request]));

    $sheet = $trip->fresh()->expenseSheet();

    expect(collect($sheet->participantList())->pluck('email'))->not->toContain(strtolower($requester->email));
});

test('the owner can decline a join request', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();
    $request = TripJoinRequest::factory()->forTrip($trip)->forUser($requester)->create();

    $this->actingAs($owner)->post(route('trips.join-requests.decline', [$trip, $request]))->assertRedirect();

    expect($request->fresh()->status)->toBe(TripJoinRequestStatus::Declined)
        ->and($trip->fresh()->isMember($requester))->toBeFalse();
});

test('a non owner cannot accept or decline requests', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $stranger = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();
    $request = TripJoinRequest::factory()->forTrip($trip)->forUser($requester)->create();

    $this->actingAs($stranger)->post(route('trips.join-requests.accept', [$trip, $request]))->assertForbidden();
    $this->actingAs($stranger)->post(route('trips.join-requests.decline', [$trip, $request]))->assertForbidden();
});

test('a member can leave and an owner can remove a member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()
        ->withCollaborator($member, TripCollaboratorRole::Member)
        ->create();

    $this->actingAs($member)->delete(route('trips.leave', $trip))->assertRedirect();
    expect($trip->fresh()->isMember($member))->toBeFalse();

    $trip = Trip::factory()->forUser($owner)->openTrip()
        ->withCollaborator($member, TripCollaboratorRole::Member)
        ->create();

    $this->actingAs($owner)->delete(route('trips.collaborators.destroy', [$trip, $member->email]))->assertRedirect();
    expect($trip->fresh()->isMember($member))->toBeFalse();
});
