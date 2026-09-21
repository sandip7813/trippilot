<?php

namespace App\Services\Expenses;

use App\Enums\ExpenseSplitType;
use InvalidArgumentException;

class ExpenseSplitter
{
    /**
     * Turns the raw split inputs into exact minor-unit amounts that always sum to $total.
     *
     * For exact splits `value` is a major-unit amount, for percentages a percent, for shares
     * a weight; equal splits ignore `value`. Entries with a zero value are dropped.
     *
     * @param  list<array{participant_id: string, value?: float|int|string|null}>  $inputs
     * @return list<array{participant_id: string, amount: int}>
     *
     * @throws InvalidArgumentException
     */
    public function resolve(ExpenseSplitType $type, int $total, array $inputs): array
    {
        if ($inputs === []) {
            throw new InvalidArgumentException('Choose at least one person to split between.');
        }

        return match ($type) {
            ExpenseSplitType::Equal => $this->allocate($total, $this->weights($inputs, fn (): int => 100)),
            ExpenseSplitType::Shares => $this->allocate($total, $this->weights($inputs, fn (float $value): int => (int) round($value * 100))),
            ExpenseSplitType::Percentage => $this->resolvePercentage($total, $inputs),
            ExpenseSplitType::Exact => $this->resolveExact($total, $inputs),
        };
    }

    /**
     * @param  list<array{participant_id: string, value?: float|int|string|null}>  $inputs
     * @return list<array{participant_id: string, amount: int}>
     */
    private function resolvePercentage(int $total, array $inputs): array
    {
        $weights = $this->weights($inputs, fn (float $value): int => (int) round($value * 100));

        if (abs(array_sum($weights) - 10000) > 1) {
            throw new InvalidArgumentException('Percentages must add up to 100%.');
        }

        return $this->allocate($total, $weights);
    }

    /**
     * @param  list<array{participant_id: string, value?: float|int|string|null}>  $inputs
     * @return list<array{participant_id: string, amount: int}>
     */
    private function resolveExact(int $total, array $inputs): array
    {
        $splits = [];

        foreach ($inputs as $input) {
            $amount = (int) round(((float) ($input['value'] ?? 0)) * 100);

            if ($amount < 0) {
                throw new InvalidArgumentException('Split amounts cannot be negative.');
            }

            if ($amount > 0) {
                $splits[] = ['participant_id' => (string) $input['participant_id'], 'amount' => $amount];
            }
        }

        if (array_sum(array_column($splits, 'amount')) !== $total) {
            throw new InvalidArgumentException('The split amounts must add up to the total.');
        }

        return $splits;
    }

    /**
     * @param  list<array{participant_id: string, value?: float|int|string|null}>  $inputs
     * @param  callable(float): int  $convert
     * @return array<string, int>
     */
    private function weights(array $inputs, callable $convert): array
    {
        $weights = [];

        foreach ($inputs as $input) {
            $weight = $convert((float) ($input['value'] ?? 0));

            if ($weight < 0) {
                throw new InvalidArgumentException('Split values cannot be negative.');
            }

            if ($weight > 0) {
                $weights[(string) $input['participant_id']] = $weight;
            }
        }

        if ($weights === []) {
            throw new InvalidArgumentException('Choose at least one person to split between.');
        }

        return $weights;
    }

    /**
     * Largest-remainder allocation, so the parts always add up to the total exactly.
     *
     * @param  array<string, int>  $weights
     * @return list<array{participant_id: string, amount: int}>
     */
    private function allocate(int $total, array $weights): array
    {
        $weightSum = array_sum($weights);
        $amounts = [];
        $remainders = [];

        foreach ($weights as $participantId => $weight) {
            $amounts[$participantId] = intdiv($total * $weight, $weightSum);
            $remainders[$participantId] = ($total * $weight) % $weightSum;
        }

        $leftover = $total - array_sum($amounts);

        arsort($remainders);

        foreach (array_keys($remainders) as $participantId) {
            if ($leftover <= 0) {
                break;
            }

            $amounts[$participantId]++;
            $leftover--;
        }

        $splits = [];

        foreach (array_keys($weights) as $participantId) {
            $splits[] = ['participant_id' => $participantId, 'amount' => $amounts[$participantId]];
        }

        return $splits;
    }
}
