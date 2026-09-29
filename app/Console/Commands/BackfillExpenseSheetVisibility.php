<?php

namespace App\Console\Commands;

use App\Enums\ExpenseSheetVisibility;
use App\Models\Trip;
use App\Models\TripExpenseSheet;
use Illuminate\Console\Command;

/**
 * One-time backfill for the expense-sheet-visibility feature. Trips created
 * before this feature default to private, which would silently take
 * expense access away from existing viewer/editor collaborators. Trips that
 * already have an expense sheet and at least one collaborator are switched
 * to shared instead, so nobody loses access they already had.
 */
class BackfillExpenseSheetVisibility extends Command
{
    protected $signature = 'trippilot:backfill-expense-sheet-visibility';

    protected $description = 'Set expense_sheet_visibility=shared on existing trips that already have collaborators and an expense sheet';

    public function handle(): int
    {
        $sheetTripIds = TripExpenseSheet::query()->pluck('trip_id')->all();

        if ($sheetTripIds === []) {
            $this->components->info('No expense sheets found. Nothing to backfill.');

            return self::SUCCESS;
        }

        $updated = 0;

        Trip::query()
            ->whereIn('id', $sheetTripIds)
            ->whereNull('expense_sheet_visibility')
            ->each(function (Trip $trip) use (&$updated): void {
                if ($trip->collaboratorEntries() === []) {
                    return;
                }

                $trip->update(['expense_sheet_visibility' => ExpenseSheetVisibility::Shared]);
                $updated++;
            });

        $this->components->info("Backfilled {$updated} trip(s) to shared expense sheet visibility.");

        return self::SUCCESS;
    }
}
