<?php

use App\Enums\TripCollaboratorRole;
use App\Models\Trip;
use App\Models\TripExpenseEntry;
use App\Models\TripExpenseSheet;
use App\Models\User;

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
    TripExpenseSheet::query()->whereNotNull('_id')->delete();
    TripExpenseEntry::withTrashed()->whereNotNull('_id')->forceDelete();
});

/**
 * @return array{0: Trip, 1: TripExpenseSheet, 2: string}
 */
function sharedTripWithSheetAndParticipant(User $owner, User $member): array
{
    $trip = Trip::factory()->forUser($owner)
        ->expenseSheetShared()
        ->withCollaborator($member, TripCollaboratorRole::Member)
        ->create();

    $sheet = TripExpenseSheet::factory()->forTrip($trip)->create([
        'participants' => [[
            'id' => 'p1',
            'name' => $member->name,
            'email' => $member->email,
            'phone' => null,
            'user_id' => $member->id,
            'archived' => false,
        ]],
    ]);

    return [$trip, $sheet, 'p1'];
}

/**
 * @return array<string, mixed>
 */
function entryPayload(string $participantId): array
{
    return [
        'type' => 'payment',
        'category' => 'stay',
        'title' => 'Campsite fee',
        'entry_date' => '2026-10-01',
        'amount' => 500,
        'payers' => [['participant_id' => $participantId, 'amount' => 500]],
        'split_type' => 'equal',
        'split_inputs' => [['participant_id' => $participantId]],
    ];
}

test('a member can create their own expense entry on a shared sheet', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$trip, , $participantId] = sharedTripWithSheetAndParticipant($owner, $member);

    $this->actingAs($member)
        ->post(route('trips.expenses.entries.store', $trip), entryPayload($participantId))
        ->assertSessionHasNoErrors();

    expect(TripExpenseEntry::query()->count())->toBe(1);
});

test('a member can edit their own entry but not another members entry', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $otherMember = User::factory()->create();
    [$trip, $sheet, $participantId] = sharedTripWithSheetAndParticipant($owner, $member);

    $this->actingAs($member)->post(route('trips.expenses.entries.store', $trip), entryPayload($participantId));
    $ownEntry = TripExpenseEntry::query()->first();

    $this->actingAs($member)
        ->patch(route('trips.expenses.entries.update', [$trip, (string) $ownEntry->id]), entryPayload($participantId))
        ->assertSessionHasNoErrors();

    $trip->update(['collaborators' => [
        ...$trip->collaboratorEntries(),
        ['user_id' => $otherMember->id, 'email' => $otherMember->email, 'role' => 'member', 'status' => 'accepted', 'added_at' => now()->toIso8601String()],
    ]]);

    $this->actingAs($otherMember)
        ->patch(route('trips.expenses.entries.update', [$trip, (string) $ownEntry->id]), entryPayload($participantId))
        ->assertForbidden();

    $this->actingAs($otherMember)
        ->delete(route('trips.expenses.entries.destroy', [$trip, (string) $ownEntry->id]))
        ->assertForbidden();
});

test('a member cannot create a sheet, manage participants, or settle', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$trip] = sharedTripWithSheetAndParticipant($owner, $member);

    $this->actingAs($member)
        ->post(route('trips.expenses.participants.store', $trip), ['name' => 'X', 'email' => 'x@example.com'])
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('trips.expenses.settle', $trip), ['notify_participants' => false])
        ->assertForbidden();
});
