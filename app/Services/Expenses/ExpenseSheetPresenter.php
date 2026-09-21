<?php

namespace App\Services\Expenses;

use App\Enums\ExpenseCategory;
use App\Models\Trip;
use App\Models\TripExpenseActivity;
use App\Models\TripExpenseEntry;
use App\Models\TripExpenseSheet;
use App\Models\User;
use App\Support\ExpenseMoney;
use Illuminate\Support\Collection;

class ExpenseSheetPresenter
{
    public function __construct(private ExpenseCalculator $calculator) {}

    /**
     * @return array<string, mixed>
     */
    public function forTrip(Trip $trip, User $viewer): array
    {
        $sheet = $trip->expenseSheet();
        $canManage = $viewer->can('manageExpenses', $trip);

        return [
            'can_manage' => $canManage,
            'suggested_participants' => $canManage ? $this->suggestedParticipants($trip) : [],
            'sheet' => $sheet === null ? null : $this->sheet($trip, $sheet),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sheet(Trip $trip, TripExpenseSheet $sheet): array
    {
        $entries = $this->entries($sheet);
        $summary = $sheet->isSettled() && is_array($sheet->settlement_snapshot)
            ? $sheet->settlement_snapshot
            : $this->summary($trip, $sheet, $entries);

        return [
            'id' => (string) $sheet->id,
            'status' => $sheet->status->value,
            'settled_at' => $sheet->settled_at?->toIso8601String(),
            'settled_by' => $sheet->settled_by['name'] ?? null,
            'participants' => collect($sheet->participantList())->map(fn (array $participant): array => [
                ...$participant,
                'is_portal_user' => $participant['user_id'] !== null,
            ])->all(),
            'entries' => $entries->map(fn (TripExpenseEntry $entry): array => $this->entry($entry))->all(),
            'summary' => $summary,
            'activities' => TripExpenseActivity::query()
                ->where('trip_id', (string) $trip->id)
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (TripExpenseActivity $activity): array => [
                    'id' => (string) $activity->id,
                    'actor' => $activity->actor['name'] ?? 'Someone',
                    'summary' => $activity->summary,
                    'by_other' => $activity->by_other,
                    'created_at' => $activity->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * Balances and totals in major units, ready for the frontend, reports and the settle snapshot.
     *
     * @param  iterable<TripExpenseEntry>  $entries
     * @return array<string, mixed>
     */
    public function summary(Trip $trip, TripExpenseSheet $sheet, iterable $entries): array
    {
        $result = $this->calculator->compute($sheet, $entries);
        $major = fn (int $minor): float => ExpenseMoney::toMajor($minor);

        return [
            'totals' => array_map($major, $result['totals']),
            'budget' => $trip->budget,
            'participants' => array_map(fn (array $person): array => [
                'participant_id' => $person['participant_id'],
                'paid' => $major($person['paid']),
                'share' => $major($person['share']),
                'sent' => $major($person['sent']),
                'received' => $major($person['received']),
                'balance' => $major($person['balance']),
            ], $result['participants']),
            'categories' => array_map(fn (array $row): array => [
                'category' => $row['category'],
                'label' => ExpenseCategory::tryFrom($row['category'])?->label() ?? $row['category'],
                'net' => $major($row['net']),
            ], $result['categories']),
            'days' => array_map(fn (array $row): array => ['date' => $row['date'], 'net' => $major($row['net'])], $result['days']),
            'settlements' => array_map(fn (array $row): array => [...$row, 'amount' => $major($row['amount'])], $result['settlements']),
        ];
    }

    /**
     * @return Collection<int, TripExpenseEntry>
     */
    public function entries(TripExpenseSheet $sheet): Collection
    {
        return TripExpenseEntry::query()
            ->where('sheet_id', (string) $sheet->id)
            ->orderBy('entry_date')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(TripExpenseEntry $entry): array
    {
        $major = fn (array $rows): array => array_map(fn (array $row): array => [...$row, 'amount' => ExpenseMoney::toMajor($row['amount'])], $rows);

        return [
            'id' => (string) $entry->id,
            'type' => $entry->type->value,
            'type_label' => $entry->type->label(),
            'category' => $entry->category,
            'category_label' => $entry->category !== null ? (ExpenseCategory::tryFrom($entry->category)?->label() ?? $entry->category) : null,
            'title' => $entry->title,
            'entry_date' => $entry->entry_date?->toDateString(),
            'amount' => ExpenseMoney::toMajor($entry->amount),
            'payers' => $major($entry->payers),
            'split_type' => $entry->split_type->value,
            'split_inputs' => $entry->split_inputs,
            'splits' => $major($entry->splits),
            'notes' => $entry->notes,
            'created_by' => $entry->created_by['name'] ?? null,
            'created_at' => $entry->created_at?->toIso8601String(),
            'updated_at' => $entry->updated_at?->toIso8601String(),
        ];
    }

    /**
     * People already on the trip who can be picked as participants with one click.
     *
     * @return list<array{name: string, email: string}>
     */
    private function suggestedParticipants(Trip $trip): array
    {
        $owner = User::query()->find((int) $trip->user_id);
        $people = $owner !== null ? [['name' => $owner->name, 'email' => $owner->email]] : [];

        foreach ($trip->collaboratorsForFrontend() as $collaborator) {
            $people[] = [
                'name' => $collaborator['name'] ?? strstr($collaborator['email'], '@', true) ?: $collaborator['email'],
                'email' => $collaborator['email'],
            ];
        }

        return $people;
    }
}
