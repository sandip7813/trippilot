<?php

namespace App\Actions\Expenses;

use App\Enums\ExpenseSheetStatus;
use App\Exceptions\ExpenseSheetException;
use App\Mail\TripExpenseSheetReopenedMail;
use App\Models\Trip;
use App\Models\TripExpenseSheet;
use App\Models\User;
use App\Services\Expenses\ExpenseActivityLogger;
use Illuminate\Support\Facades\Mail;

class ReopenExpenseSheet
{
    public function __construct(private ExpenseActivityLogger $logger) {}

    public function __invoke(Trip $trip, TripExpenseSheet $sheet, User $actor, string $reason): void
    {
        if (! $sheet->isSettled()) {
            throw new ExpenseSheetException('This expense sheet is not settled.');
        }

        $sheet->update([
            'status' => ExpenseSheetStatus::Open,
            'settled_at' => null,
            'settled_by' => null,
            'settlement_snapshot' => null,
        ]);

        $this->logger->log($trip, $actor, 'sheet_reopened', 'reopened the settled expense sheet. Reason: '.$reason);

        foreach ($sheet->participantList() as $participant) {
            if (! $participant['archived']) {
                Mail::to($participant['email'])->queue(new TripExpenseSheetReopenedMail($trip, $participant['id'], $actor->name, $reason));
            }
        }
    }
}
