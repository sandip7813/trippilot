<?php

namespace App\Support;

class BudgetBreakdownNormalizer
{
    /**
     * @param  array<string, mixed>  $budget
     * @return array{
     *     estimated_total: float|null,
     *     breakdown: array<string, float>,
     *     currency: string
     * }
     */
    public static function normalize(array $budget, ?string $defaultCurrency = null): array
    {
        $defaultCurrency ??= strtoupper((string) config('trippilot.currency', 'INR'));

        $currency = is_string($budget['currency'] ?? null) && $budget['currency'] !== ''
            ? strtoupper($budget['currency'])
            : $defaultCurrency;

        $breakdown = self::extractBreakdown($budget);
        $estimatedTotal = self::extractEstimatedTotal($budget);

        if ($estimatedTotal === null && $breakdown !== []) {
            $estimatedTotal = array_sum($breakdown);
        }

        return [
            'estimated_total' => $estimatedTotal,
            'breakdown' => $breakdown,
            'currency' => $currency,
        ];
    }

    /**
     * @param  array<string, mixed>  $budget
     * @return array<string, float>
     */
    private static function extractBreakdown(array $budget): array
    {
        $nested = $budget['breakdown'] ?? null;

        if (is_array($nested)) {
            if (array_is_list($nested)) {
                return self::fromListBreakdown($nested);
            }

            return self::fromAssociativeBreakdown($nested);
        }

        return self::fromAssociativeBreakdown($budget);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, float>
     */
    private static function fromAssociativeBreakdown(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['estimated_total', 'total', 'breakdown', 'currency'], true)) {
                continue;
            }

            $amount = self::toAmount($value);

            if ($amount !== null) {
                $result[$key] = $amount;
            }
        }

        return $result;
    }

    /**
     * @param  list<mixed>  $items
     * @return array<string, float>
     */
    private static function fromListBreakdown(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = $item['category'] ?? $item['label'] ?? $item['name'] ?? null;
            $amount = self::toAmount($item['amount'] ?? $item['value'] ?? null);

            if (is_string($key) && $key !== '' && $amount !== null) {
                $result[$key] = $amount;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $budget
     */
    private static function extractEstimatedTotal(array $budget): ?float
    {
        return self::toAmount($budget['estimated_total'] ?? $budget['total'] ?? null);
    }

    private static function toAmount(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }
}
