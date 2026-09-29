<?php

use App\Actions\Trips\PublishOpenTrip;
use App\Enums\ExpenseSheetVisibility;
use App\Enums\TripCollaboratorRole;
use App\Enums\TripVisibility;
use App\Models\Trip;
use App\Models\TripExpenseSheet;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
});

test('owner can publish and unpublish their trip', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create(['visibility' => null]);

    $publish = app(PublishOpenTrip::class);
    $publish->publish($trip, $owner);

    expect($trip->fresh()->isPublic())->toBeTrue();

    $publish->unpublish($trip);

    expect($trip->fresh()->isPublic())->toBeFalse();
});

test('publishing fails when required group details are missing', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $publish = app(PublishOpenTrip::class);

    expect(fn () => $publish->publish($trip, $owner))->toThrow(RuntimeException::class);
    expect($trip->fresh()->isPublic())->toBeFalse();

    $trip->update(['open_trip' => [
        'category' => 'trek',
        'max_group_size' => 6,
        'cost_model' => 'cost_sharing',
    ]]);

    $publish->publish($trip->fresh(), $owner);

    expect($trip->fresh()->isPublic())->toBeTrue();
});

test('publishing is capped at the configured active limit', function () {
    config(['trippilot.open_trips.max_active_per_user' => 2]);

    $owner = User::factory()->create();
    $publish = app(PublishOpenTrip::class);

    Trip::factory()->forUser($owner)->openTrip()->create();
    Trip::factory()->forUser($owner)->openTrip()->create();
    $overLimit = Trip::factory()->forUser($owner)->openTrip()->create(['visibility' => null]);

    expect(fn () => $publish->publish($overLimit, $owner))
        ->toThrow(RuntimeException::class, 'active open trips');

    expect($overLimit->fresh()->isPublic())->toBeFalse();
});

test('a past public trip does not count toward the active limit', function () {
    config(['trippilot.open_trips.max_active_per_user' => 1]);

    $owner = User::factory()->create();
    $publish = app(PublishOpenTrip::class);

    Trip::factory()->forUser($owner)->openTrip()->create([
        'end_date' => now()->subMonth(),
    ]);

    $newTrip = Trip::factory()->forUser($owner)->openTrip()->create(['visibility' => null]);
    $publish->publish($newTrip, $owner);

    expect($newTrip->fresh()->isPublic())->toBeTrue();
});

test('only the owner can publish', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    expect($intruder->can('publish', $trip))->toBeFalse()
        ->and($owner->can('publish', $trip))->toBeTrue();
});

test('anyone including a guest can view a public trip', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $guest = null;

    expect(Gate::forUser($guest)->allows('viewPublic', $trip))->toBeTrue();

    $trip->update(['visibility' => TripVisibility::Private]);

    expect(Gate::forUser($guest)->allows('viewPublic', $trip))->toBeFalse();
});

test('expense sheet is private to the owner by default', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)
        ->withCollaborator($collaborator, TripCollaboratorRole::Editor)
        ->create();

    expect($owner->can('viewExpenses', $trip))->toBeTrue()
        ->and($collaborator->can('viewExpenses', $trip))->toBeFalse()
        ->and($collaborator->can('manageExpenses', $trip))->toBeFalse();
});

test('shared expense sheet opens access to collaborators by role', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $member = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)
        ->expenseSheetShared()
        ->withCollaborator($editor, TripCollaboratorRole::Editor)
        ->withCollaborator($member, TripCollaboratorRole::Member)
        ->create();

    expect($editor->can('viewExpenses', $trip))->toBeTrue()
        ->and($editor->can('manageExpenses', $trip))->toBeTrue()
        ->and($member->can('viewExpenses', $trip))->toBeTrue()
        // Members may only manage their own entries; that finer-grained
        // check happens at the entry level, not this blanket ability.
        ->and($member->can('manageExpenses', $trip))->toBeFalse();
});

test('the backfill command shares expense sheets for existing trips with collaborators', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)
        ->withCollaborator($collaborator, TripCollaboratorRole::Viewer)
        ->create();

    TripExpenseSheet::factory()->create(['trip_id' => (string) $trip->id]);

    $this->artisan('trippilot:backfill-expense-sheet-visibility')->assertExitCode(0);

    expect($trip->fresh()->expenseSheetVisibility())->toBe(ExpenseSheetVisibility::Shared);
});

test('a trip is joinable only while public, not past, and not full', function () {
    $owner = User::factory()->create();

    $joinable = Trip::factory()->forUser($owner)->openTrip()->create();
    expect($joinable->isJoinable())->toBeTrue();

    $past = Trip::factory()->forUser($owner)->openTrip()->create(['end_date' => now()->subDay()]);
    expect($past->isPastTrip())->toBeTrue()
        ->and($past->isJoinable())->toBeFalse();

    $full = Trip::factory()->forUser($owner)->openTrip()->create();
    $full->update(['open_trip' => array_merge($full->openTripDetails(), ['max_group_size' => 1])]);
    $member = User::factory()->create();
    $full->update(['collaborators' => [[
        'user_id' => $member->id,
        'email' => $member->email,
        'role' => TripCollaboratorRole::Member->value,
        'status' => 'accepted',
        'added_at' => now()->toIso8601String(),
    ]]]);
    expect($full->fresh()->isJoinable())->toBeFalse();
});
