<?php

namespace App\Actions\Expenses;

use App\Enums\ExpenseSheetStatus;
use App\Exceptions\ExpenseSheetException;
use App\Mail\TripExpenseSheetSettledMail;
use App\Models\Trip;
use App\Models\TripExpenseSheet;
use App\Models\User;
use App\Services\Expenses\ExpenseActivityLogger;
use App\Services\Expenses\ExpenseSheetPresenter;
use Illuminate\Support\Facades\Mail;

class SettleExpenseSheet
{
    public function __construct(
        private ExpenseSheetPresenter $presenter,
        private ExpenseActivityLogger $logger,
    ) {}

    public function __invoke(Trip $trip, TripExpenseSheet $sheet, User $actor, bool $notifyParticipants): void
    {
        if ($sheet->isSettled()) {
            throw new ExpenseSheetException('This expense sheet is already settled.');
        }

        $summary = $this->presenter->summary($trip, $sheet, $this->presenter->entries($sheet));

        $sheet->update([
            'status' => ExpenseSheetStatus::Settled,
            'settled_at' => now(),
            'settled_by' => ['user_id' => $actor->id, 'name' => $actor->name],
            'settlement_snapshot' => $summary,
        ]);

        $this->logger->log($trip, $actor, 'sheet_settled', 'closed and settled the expense sheet');

        if ($notifyParticipants) {
            foreach ($sheet->participantList() as $participant) {
                if (! $participant['archived']) {
                    Mail::to($participant['email'])->queue(new TripExpenseSheetSettledMail($trip, $sheet->refresh(), $participant['id'], $actor->name));
                }
            }
        }
    }
}
