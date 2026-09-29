<?php

namespace App\Actions\Trips;

use App\Actions\Expenses\ManageExpenseParticipants;
use App\Enums\ExpenseSheetVisibility;
use App\Enums\TripCollaboratorRole;
use App\Enums\TripJoinRequestStatus;
use App\Exceptions\ExpenseSheetException;
use App\Models\Trip;
use App\Models\TripJoinRequest;
use App\Models\User;
use App\Notifications\TripJoinRequestDecidedNotification;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Accepting adds the requester as a `member` collaborator (and, when the
 * trip's expense sheet is shared, as an expense participant). Seats are
 * re-checked at accept time so two pending requests can't oversell a full
 * group.
 */
class DecideTripJoinRequest
{
    public function __construct(
        private readonly ManageExpenseParticipants $expenseParticipants,
    ) {}

    public function accept(Trip $trip, TripJoinRequest $request, User $decider): void
    {
        $this->guardPending($request);

        if (! $trip->hasOpenSeats()) {
            throw new RuntimeException('This trip is full.');
        }

        $requester = User::query()->find($request->user_id);

        if ($requester === null) {
            throw new RuntimeException('This user no longer exists.');
        }

        $entries = collect($trip->collaboratorEntries())
            ->reject(fn (array $entry): bool => (int) ($entry['user_id'] ?? 0) === $requester->id)
            ->push([
                'user_id' => $requester->id,
                'email' => $requester->email,
                'role' => TripCollaboratorRole::Member->value,
                'status' => 'accepted',
                'added_at' => Carbon::now()->toIso8601String(),
            ])
            ->values()
            ->all();

        $trip->update(['collaborators' => $entries]);

        if ($trip->expenseSheetVisibility() === ExpenseSheetVisibility::Shared) {
            $sheet = $trip->expenseSheet();

            if ($sheet !== null) {
                try {
                    $this->expenseParticipants->add($sheet, [
                        'name' => $requester->name,
                        'email' => $requester->email,
                        'phone' => $request->phone,
                    ]);
                } catch (ExpenseSheetException) {
                    // Already a participant (e.g. rejoining) — nothing to do.
                }
            }
        }

        $request->update([
            'status' => TripJoinRequestStatus::Accepted,
            'decided_at' => Carbon::now(),
            'decided_by' => $decider->id,
        ]);

        $requester->notify(TripJoinRequestDecidedNotification::forTrip($trip, TripJoinRequestStatus::Accepted));
    }

    public function decline(Trip $trip, TripJoinRequest $request, User $decider): void
    {
        $this->guardPending($request);

        $request->update([
            'status' => TripJoinRequestStatus::Declined,
            'decided_at' => Carbon::now(),
            'decided_by' => $decider->id,
        ]);

        User::query()->find($request->user_id)
            ?->notify(TripJoinRequestDecidedNotification::forTrip($trip, TripJoinRequestStatus::Declined));
    }

    private function guardPending(TripJoinRequest $request): void
    {
        if (! $request->isPending()) {
            throw new RuntimeException('This request has already been decided.');
        }
    }
}
