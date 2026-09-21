<?php

namespace App\Actions\Expenses;

use App\Enums\ExpenseSheetStatus;
use App\Exceptions\ExpenseSheetException;
use App\Models\Trip;
use App\Models\TripExpenseSheet;
use App\Models\User;
use App\Services\Expenses\ExpenseActivityLogger;

class CreateExpenseSheet
{
    public function __construct(
        private ExpenseActivityLogger $logger,
        private ManageExpenseParticipants $participants,
    ) {}

    /**
     * @param  list<array{name: string, email: string, phone?: string|null}>  $participants
     */
    public function __invoke(Trip $trip, User $actor, array $participants): TripExpenseSheet
    {
        if (TripExpenseSheet::query()->where('trip_id', (string) $trip->id)->exists()) {
            throw new ExpenseSheetException('This trip already has an expense sheet.');
        }

        $sheet = TripExpenseSheet::query()->create([
            'trip_id' => (string) $trip->id,
            'status' => ExpenseSheetStatus::Open,
            'participants' => [],
            'created_by' => ['user_id' => $actor->id, 'name' => $actor->name],
        ]);

        foreach ($participants as $participant) {
            $this->participants->add($sheet, $participant);
        }

        $this->logger->log($trip, $actor, 'sheet_created', 'created the expense sheet');

        return $sheet->refresh();
    }
}
