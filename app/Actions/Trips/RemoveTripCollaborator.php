<?php

namespace App\Actions\Trips;

use App\Actions\Expenses\ManageExpenseParticipants;
use App\Enums\ExpenseSheetVisibility;
use App\Enums\TripCollaboratorRole;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\TripMembershipEndedNotification;

class RemoveTripCollaborator
{
    public function __construct(
        private readonly ManageExpenseParticipants $expenseParticipants,
    ) {}

    public function __invoke(Trip $trip, string $email): void
    {
        $removedEntry = collect($trip->collaboratorEntries())
            ->first(fn (array $entry): bool => strcasecmp((string) $entry['email'], $email) === 0);

        $entries = collect($trip->collaboratorEntries())
            ->reject(fn (array $entry): bool => strcasecmp((string) $entry['email'], $email) === 0)
            ->values()
            ->all();

        $trip->update(['collaborators' => $entries]);

        if ($removedEntry === null || ($removedEntry['role'] ?? null) !== TripCollaboratorRole::Member->value) {
            return;
        }

        $this->archiveExpenseParticipant($trip, $email);

        $removedUser = User::query()->find((int) ($removedEntry['user_id'] ?? 0));
        $removedUser?->notify(TripMembershipEndedNotification::removedByOwner($trip));
    }

    private function archiveExpenseParticipant(Trip $trip, string $email): void
    {
        if ($trip->expenseSheetVisibility() !== ExpenseSheetVisibility::Shared) {
            return;
        }

        $sheet = $trip->expenseSheet();
        $match = collect($sheet?->participantList() ?? [])->firstWhere('email', strtolower($email));

        if ($sheet !== null && $match !== null) {
            $this->expenseParticipants->remove($sheet, $match['id']);
        }
    }
}
