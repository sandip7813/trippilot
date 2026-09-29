<?php

use App\Enums\TripCollaboratorRole;
use App\Models\Trip;
use App\Models\User;

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
});

test('a member never sees budget, notes, or chat on the private trip page', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)
        ->withCollaborator($member, TripCollaboratorRole::Member)
        ->create([
            'budget' => 99999,
            'notes' => 'Owner-only secret notes',
            'chat_messages' => [['role' => 'user', 'content' => 'private chat']],
        ]);

    $payload = $trip->toFrontend($member);

    expect($payload['budget'])->toBeNull()
        ->and($payload['notes'])->toBeNull()
        ->and($payload['chat_messages'])->toBe([]);
});

test('a viewer or editor keeps their existing full access (unchanged by membership work)', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)
        ->withCollaborator($viewer, TripCollaboratorRole::Viewer)
        ->create(['budget' => 5000, 'notes' => 'visible to viewer']);

    $payload = $trip->toFrontend($viewer);

    expect($payload['budget'])->toBe(5000.0)
        ->and($payload['notes'])->toBe('visible to viewer');
});

test('itinerary is hidden from members unless the owner shares it', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)
        ->withCollaborator($member, TripCollaboratorRole::Member)
        ->withItinerary()
        ->create(['open_trip' => ['share_itinerary_with_members' => false]]);

    $payload = $trip->toFrontend($member);

    expect($payload['itinerary']['days'])->toBe([]);

    $trip->update(['open_trip' => ['share_itinerary_with_members' => true]]);
    $payload = $trip->fresh()->toFrontend($member);

    expect($payload['itinerary']['days'])->not->toBe([]);
});

test('other member names stay hidden unless the owner opts in', function () {
    $owner = User::factory()->create();
    $memberA = User::factory()->create();
    $memberB = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)
        ->withCollaborator($memberA, TripCollaboratorRole::Member)
        ->withCollaborator($memberB, TripCollaboratorRole::Member)
        ->create(['open_trip' => ['member_names_visible' => false]]);

    expect($trip->toFrontend($memberA)['collaborators'])->toBe([]);

    $trip->update(['open_trip' => ['member_names_visible' => true]]);
    $visible = $trip->fresh()->toFrontend($memberA)['collaborators'];
    $names = collect($visible)->pluck('name');

    expect($names)->toContain($memberB->first_name)
        ->and(json_encode($visible))->not->toContain($memberB->email);
});

test('a member can create their own expense entry when the sheet is shared, but not manage others', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $otherMember = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)
        ->expenseSheetShared()
        ->withCollaborator($member, TripCollaboratorRole::Member)
        ->withCollaborator($otherMember, TripCollaboratorRole::Member)
        ->create();

    expect($member->can('createExpenseEntry', $trip))->toBeTrue();
});

test('a member cannot manage expenses at all when the sheet is private', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)
        ->withCollaborator($member, TripCollaboratorRole::Member)
        ->create();

    expect($member->can('createExpenseEntry', $trip))->toBeFalse();
});
