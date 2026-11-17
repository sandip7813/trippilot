<?php

namespace App\Services\Credits;

use App\Enums\CreditService;
use App\Models\CreditCost;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Reads and updates the credit cost of each billable AI service. Costs set by
 * a super admin are stored in the database; any service without a stored
 * cost falls back to its enum default, so a missing row never makes it free.
 */
class CreditCosts
{
    private const CACHE_KEY = 'credit_costs:all';

    public function for(CreditService $service): int
    {
        return $this->all()[$service->value];
    }

    /**
     * @return array<string, int> credits keyed by CreditService value, in enum order
     */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => CreditCost::query()
            ->get()
            ->mapWithKeys(fn (CreditCost $cost): array => [$cost->service->value => $cost->credits])
            ->all());

        $costs = [];

        foreach (CreditService::cases() as $service) {
            $costs[$service->value] = (int) ($stored[$service->value] ?? $service->defaultCredits());
        }

        return $costs;
    }

    /**
     * @param  array<string, int>  $credits  credits keyed by CreditService value
     */
    public function update(array $credits, User $updatedBy): void
    {
        $current = $this->all();

        foreach (CreditService::cases() as $service) {
            if (! array_key_exists($service->value, $credits)) {
                continue;
            }

            $newCredits = (int) $credits[$service->value];

            if ($current[$service->value] === $newCredits) {
                continue;
            }

            CreditCost::query()->updateOrCreate(
                ['service' => $service->value],
                ['credits' => $newCredits, 'updated_by' => $updatedBy->id],
            );
        }

        Cache::forget(self::CACHE_KEY);
    }
}
