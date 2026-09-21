<?php

namespace App\Services\Expenses;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseEntryType;
use App\Models\TripExpenseEntry;
use App\Models\TripExpenseSheet;
use Illuminate\Support\Collection;

class ExpenseCalculator
{
    /**
     * Everything is in minor units. A participant's balance is what they paid (net of refunds
     * they received) minus their share, adjusted by personal payments: positive means they
     * are owed money, negative means they owe money.
     *
     * @param  iterable<TripExpenseEntry>  $entries
     * @return array{
     *     totals: array{gross: int, refunded: int, net: int},
     *     participants: list<array{participant_id: string, paid: int, share: int, sent: int, received: int, balance: int}>,
     *     categories: list<array{category: string, net: int}>,
     *     days: list<array{date: string|null, net: int}>,
     *     settlements: list<array{from: string, to: string, amount: int}>,
     * }
     */
    public function compute(TripExpenseSheet $sheet, iterable $entries): array
    {
        $entries = collect($entries);
        $people = [];

        foreach ($sheet->participantList() as $participant) {
            $people[$participant['id']] = ['participant_id' => $participant['id'], 'paid' => 0, 'share' => 0, 'sent' => 0, 'received' => 0, 'balance' => 0];
        }

        $gross = 0;
        $refunded = 0;
        $categories = [];
        $days = [];

        foreach ($entries as $entry) {
            $type = $entry->type;

            foreach ($entry->payers as $payer) {
                $key = $type === ExpenseEntryType::Transfer ? 'sent' : 'paid';
                $people[$payer['participant_id']][$key] = ($people[$payer['participant_id']][$key] ?? 0) + $type->sign() * $payer['amount'];
            }

            foreach ($entry->splits as $split) {
                $key = $type === ExpenseEntryType::Transfer ? 'received' : 'share';
                $people[$split['participant_id']][$key] = ($people[$split['participant_id']][$key] ?? 0) + $type->sign() * $split['amount'];
            }

            if (! $type->countsTowardSpend()) {
                continue;
            }

            if ($type === ExpenseEntryType::Refund) {
                $refunded += $entry->amount;
            } else {
                $gross += $entry->amount;
            }

            $category = $entry->category ?? ExpenseCategory::Other->value;
            $categories[$category] = ($categories[$category] ?? 0) + $type->sign() * $entry->amount;

            $day = $entry->entry_date?->toDateString() ?? '';
            $days[$day] = ($days[$day] ?? 0) + $type->sign() * $entry->amount;
        }

        foreach ($people as &$person) {
            $person['balance'] = $person['paid'] - $person['share'] + $person['sent'] - $person['received'];
        }

        unset($person);

        ksort($days);

        return [
            'totals' => ['gross' => $gross, 'refunded' => $refunded, 'net' => $gross - $refunded],
            'participants' => array_values($people),
            'categories' => $this->categoryTotals($categories),
            'days' => collect($days)->map(fn (int $net, string $date): array => ['date' => $date === '' ? null : $date, 'net' => $net])->values()->all(),
            'settlements' => $this->settlements($people),
        ];
    }

    /**
     * @param  array<string, int>  $totals
     * @return list<array{category: string, net: int}>
     */
    private function categoryTotals(array $totals): array
    {
        return collect($totals)
            ->map(fn (int $net, string $category): array => ['category' => $category, 'net' => $net])
            ->sortByDesc('net')
            ->values()
            ->all();
    }

    /**
     * Greedy minimal-transfer plan: the biggest debtor pays the biggest creditor first.
     *
     * @param  array<string, array{participant_id: string, balance: int}>  $people
     * @return list<array{from: string, to: string, amount: int}>
     */
    private function settlements(array $people): array
    {
        /** @var Collection<int, array{id: string, amount: int}> $debtors */
        $debtors = collect($people)
            ->filter(fn (array $person): bool => $person['balance'] < 0)
            ->map(fn (array $person): array => ['id' => $person['participant_id'], 'amount' => -$person['balance']])
            ->sortByDesc('amount')
            ->values();
        $creditors = collect($people)
            ->filter(fn (array $person): bool => $person['balance'] > 0)
            ->map(fn (array $person): array => ['id' => $person['participant_id'], 'amount' => $person['balance']])
            ->sortByDesc('amount')
            ->values();

        $debtors = $debtors->all();
        $creditors = $creditors->all();
        $plan = [];
        $d = 0;
        $c = 0;

        while (isset($debtors[$d], $creditors[$c])) {
            $amount = min($debtors[$d]['amount'], $creditors[$c]['amount']);
            $plan[] = ['from' => $debtors[$d]['id'], 'to' => $creditors[$c]['id'], 'amount' => $amount];
            $debtors[$d]['amount'] -= $amount;
            $creditors[$c]['amount'] -= $amount;

            if ($debtors[$d]['amount'] === 0) {
                $d++;
            }

            if ($creditors[$c]['amount'] === 0) {
                $c++;
            }
        }

        return $plan;
    }
}
