<?php

namespace App\Actions\Expenses;

use App\Models\Trip;
use App\Models\TripExpenseEntry;
use App\Models\User;
use App\Services\Expenses\ExpenseActivityLogger;
use App\Support\ExpenseMoney;

class DeleteExpenseEntry
{
    public function __construct(private ExpenseActivityLogger $logger) {}

    public function __invoke(Trip $trip, User $actor, TripExpenseEntry $entry): void
    {
        $this->logger->log(
            $trip,
            $actor,
            'entry_deleted',
            'deleted '.strtolower($entry->type->label()).' "'.$entry->title.'" ('.ExpenseMoney::format($entry->amount).')',
            $entry,
        );

        $entry->delete();
    }
}
