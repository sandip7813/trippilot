<?php

use App\Enums\ExpenseSheetVisibility;
use App\Enums\TripCollaboratorRole;
use App\Models\Trip;
use App\Models\User;

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
});

test('the owner can publish a trip from the edit page controls', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create(['visibility' => null]);

    $this->actingAs($owner)->post(route('trips.publish', $trip))->assertRedirect();

    expect($trip->fresh()->isPublic())->toBeTrue();
});

test('publishing without the required group details fails with a helpful error', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($owner)
        ->post(route('trips.publish', $trip))
        ->assertSessionHasErrors('visibility');

    expect($trip->fresh()->isPublic())->toBeFalse();
});

test('an editor cannot publish someone elses trip', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)
        ->withCollaborator($editor, TripCollaboratorRole::Editor)
        ->create();

    $this->actingAs($editor)->post(route('trips.publish', $trip))->assertForbidden();

    expect($trip->fresh()->isPublic())->toBeFalse();
});

test('publishing past the active limit shows an error instead of a 500', function () {
    config(['trippilot.open_trips.max_active_per_user' => 1]);

    $owner = User::factory()->create();
    Trip::factory()->forUser($owner)->openTrip()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create(['visibility' => null]);

    $this->actingAs($owner)
        ->post(route('trips.publish', $trip))
        ->assertSessionHasErrors('visibility');

    expect($trip->fresh()->isPublic())->toBeFalse();
});

test('the owner can update the group details', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $this->actingAs($owner)->put(route('trips.open-trip.update', $trip), [
        'category' => 'bike',
        'difficulty' => 'challenging',
        'max_group_size' => 10,
        'requirements' => 'Own bike required',
        'cost_model' => 'pay_own',
        'share_itinerary_with_members' => true,
        'member_names_visible' => true,
    ])->assertRedirect();

    $details = $trip->fresh()->openTripDetails();

    expect($details['category'])->toBe('bike')
        ->and($details['max_group_size'])->toBe(10)
        ->and($details['requirements'])->toBe('Own bike required')
        ->and($details['cost_model'])->toBe('pay_own')
        ->and($details['member_names_visible'])->toBeTrue();
});

test('a non owner cannot update group details', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $this->actingAs($stranger)
        ->put(route('trips.open-trip.update', $trip), ['category' => 'trek'])
        ->assertForbidden();
});

test('the owner can toggle expense sheet visibility', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    expect($trip->expenseSheetVisibility())->toBe(ExpenseSheetVisibility::Private);

    $this->actingAs($owner)
        ->put(route('trips.expense-sheet-visibility.update', $trip), ['expense_sheet_visibility' => 'shared'])
        ->assertRedirect();

    expect($trip->fresh()->expenseSheetVisibility())->toBe(ExpenseSheetVisibility::Shared);
});

test('a collaborator cannot change expense sheet visibility', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)
        ->withCollaborator($editor, TripCollaboratorRole::Editor)
        ->create();

    $this->actingAs($editor)
        ->put(route('trips.expense-sheet-visibility.update', $trip), ['expense_sheet_visibility' => 'shared'])
        ->assertForbidden();
});

test('the edit page only shows open trip settings to the owner', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)
        ->withCollaborator($editor, TripCollaboratorRole::Editor)
        ->create();

    $this->actingAs($owner)->get(route('trips.edit', $trip))->assertInertia(fn ($page) => $page
        ->where('trip.is_owner', true)
        ->where('trip.visibility', 'private')
    );

    $this->actingAs($editor)->get(route('trips.edit', $trip))->assertInertia(fn ($page) => $page
        ->where('trip.is_owner', false)
        ->where('trip.visibility', null)
        ->where('trip.open_trip', null)
    );
});
