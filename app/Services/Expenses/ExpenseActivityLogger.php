<?php

namespace App\Services\Expenses;

use App\Models\Trip;
use App\Models\TripExpenseActivity;
use App\Models\TripExpenseEntry;
use App\Models\User;
use App\Support\ExpenseMoney;

class ExpenseActivityLogger
{
    public function log(Trip $trip, User $actor, string $action, string $summary, ?TripExpenseEntry $entry = null): TripExpenseActivity
    {
        $creatorId = $entry?->created_by['user_id'] ?? null;

        return TripExpenseActivity::query()->create([
            'trip_id' => (string) $trip->id,
            'actor' => ['user_id' => $actor->id, 'name' => $actor->name],
            'action' => $action,
            'summary' => $summary,
            'by_other' => $creatorId !== null && (int) $creatorId !== $actor->id,
            'entry_id' => $entry !== null ? (string) $entry->id : null,
        ]);
    }

    /**
     * Human readable list of what changed between two versions of an entry.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function describeChanges(array $before, array $after): string
    {
        $changes = [];

        if (($before['amount'] ?? null) !== ($after['amount'] ?? null)) {
            $changes[] = 'amount '.ExpenseMoney::format((int) $before['amount']).' → '.ExpenseMoney::format((int) $after['amount']);
        }

        foreach (['title' => 'title', 'entry_date' => 'date', 'category' => 'category'] as $key => $label) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $changes[] = $label.' "'.($before[$key] ?? '—').'" → "'.($after[$key] ?? '—').'"';
            }
        }

        foreach (['payers' => 'payers', 'splits' => 'split'] as $key => $label) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $changes[] = $label.' changed';
            }
        }

        return $changes === [] ? 'no visible changes' : implode(', ', $changes);
    }
}
