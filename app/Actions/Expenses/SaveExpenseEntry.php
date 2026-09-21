<?php

namespace App\Actions\Expenses;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseEntryType;
use App\Enums\ExpenseSplitType;
use App\Exceptions\ExpenseSheetException;
use App\Models\Trip;
use App\Models\TripExpenseEntry;
use App\Models\TripExpenseSheet;
use App\Models\User;
use App\Services\Expenses\ExpenseActivityLogger;
use App\Services\Expenses\ExpenseSplitter;
use App\Support\ExpenseMoney;
use InvalidArgumentException;

class SaveExpenseEntry
{
    public function __construct(
        private ExpenseSplitter $splitter,
        private ExpenseActivityLogger $logger,
    ) {}

    /**
     * Creates the entry, or updates it when $entry is given. Amounts in $data are major units.
     *
     * @param  array{
     *     type: string,
     *     category?: string|null,
     *     title: string,
     *     entry_date: string,
     *     amount: float|int|string,
     *     payers: list<array{participant_id: string, amount: float|int|string}>,
     *     split_type: string,
     *     split_inputs: list<array{participant_id: string, value?: float|int|string|null}>,
     *     notes?: string|null,
     * }  $data
     */
    public function __invoke(Trip $trip, TripExpenseSheet $sheet, User $actor, array $data, ?TripExpenseEntry $entry = null): TripExpenseEntry
    {
        $type = ExpenseEntryType::from($data['type']);
        $splitType = $type === ExpenseEntryType::Transfer ? ExpenseSplitType::Exact : ExpenseSplitType::from($data['split_type']);
        $total = ExpenseMoney::toMinor($data['amount']);

        if ($total <= 0) {
            throw new ExpenseSheetException('The amount must be greater than zero.');
        }

        $category = $type === ExpenseEntryType::Transfer
            ? null
            : (ExpenseCategory::tryFrom((string) ($data['category'] ?? ''))?->value ?? ExpenseCategory::Other->value);

        $payers = $this->payers($sheet, $total, $data['payers']);
        $splitInputs = $type === ExpenseEntryType::Transfer
            ? $this->transferInputs($data)
            : array_values($data['split_inputs']);

        $this->assertParticipantsExist($sheet, array_column($splitInputs, 'participant_id'));

        try {
            $splits = $this->splitter->resolve($splitType, $total, $splitInputs);
        } catch (InvalidArgumentException $exception) {
            throw new ExpenseSheetException($exception->getMessage());
        }

        if ($type === ExpenseEntryType::Transfer && $payers[0]['participant_id'] === $splits[0]['participant_id']) {
            throw new ExpenseSheetException('A personal payment needs two different people.');
        }

        $attributes = [
            'trip_id' => (string) $trip->id,
            'sheet_id' => (string) $sheet->id,
            'category' => $category,
            'type' => $type,
            'title' => trim($data['title']),
            'entry_date' => $data['entry_date'],
            'amount' => $total,
            'payers' => $payers,
            'split_type' => $splitType,
            'split_inputs' => $splitInputs,
            'splits' => $splits,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ];

        if ($entry === null) {
            $entry = TripExpenseEntry::query()->create([
                ...$attributes,
                'created_by' => ['user_id' => $actor->id, 'name' => $actor->name],
            ]);

            $this->logger->log($trip, $actor, 'entry_created', 'added '.$this->label($type, $entry->title, $total), $entry);

            return $entry;
        }

        $before = $this->snapshot($entry);
        $entry->update($attributes);
        $after = $this->snapshot($entry->refresh());

        $this->logger->log(
            $trip,
            $actor,
            'entry_updated',
            'edited '.$this->label($type, $entry->title, $total).': '.$this->logger->describeChanges($before, $after),
            $entry,
        );

        return $entry;
    }

    /**
     * @param  list<array{participant_id: string, amount: float|int|string}>  $payers
     * @return list<array{participant_id: string, amount: int}>
     */
    private function payers(TripExpenseSheet $sheet, int $total, array $payers): array
    {
        $resolved = collect($payers)
            ->map(fn (array $payer): array => ['participant_id' => (string) $payer['participant_id'], 'amount' => ExpenseMoney::toMinor($payer['amount'])])
            ->filter(fn (array $payer): bool => $payer['amount'] > 0)
            ->values()
            ->all();

        if ($resolved === []) {
            throw new ExpenseSheetException('Choose who paid.');
        }

        if (count(array_unique(array_column($resolved, 'participant_id'))) !== count($resolved)) {
            throw new ExpenseSheetException('A person can only be listed once as a payer.');
        }

        if (array_sum(array_column($resolved, 'amount')) !== $total) {
            throw new ExpenseSheetException('The payer amounts must add up to the total.');
        }

        $this->assertParticipantsExist($sheet, array_column($resolved, 'participant_id'));

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{participant_id: string, value: float|int|string}>
     */
    private function transferInputs(array $data): array
    {
        $toParticipantId = $data['split_inputs'][0]['participant_id'] ?? null;

        if ($toParticipantId === null) {
            throw new ExpenseSheetException('Choose who received the money.');
        }

        return [['participant_id' => (string) $toParticipantId, 'value' => $data['amount']]];
    }

    /**
     * @param  list<string>  $participantIds
     */
    private function assertParticipantsExist(TripExpenseSheet $sheet, array $participantIds): void
    {
        $known = array_column($sheet->participantList(), 'id');

        foreach ($participantIds as $participantId) {
            if (! in_array($participantId, $known, true)) {
                throw new ExpenseSheetException('One of the selected people is not on this sheet.');
            }
        }
    }

    private function label(ExpenseEntryType $type, string $title, int $total): string
    {
        return strtolower($type->label()).' "'.$title.'" ('.ExpenseMoney::format($total).')';
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TripExpenseEntry $entry): array
    {
        return [
            'title' => $entry->title,
            'amount' => $entry->amount,
            'entry_date' => $entry->entry_date?->toDateString(),
            'category' => $entry->category,
            'payers' => $entry->payers,
            'splits' => $entry->splits,
        ];
    }
}
