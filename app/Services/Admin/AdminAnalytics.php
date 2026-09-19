<?php

namespace App\Services\Admin;

use App\Enums\AiUsageFeature;
use App\Enums\TripScope;
use App\Enums\TripType;
use App\Models\AiUsageLog;
use App\Models\Trip;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminAnalytics
{
    public const CACHE_KEY = 'admin:analytics';

    private const DAYS = 30;

    private const MONTHS = 6;

    private const TOP_LIMIT = 8;

    /**
     * @return array{
     *     trips: array{
     *         total: int,
     *         vacation: int,
     *         road: int,
     *         domestic: int,
     *         international: int,
     *         by_month: list<array{label: string, count: int}>,
     *         top_destinations: list<array{label: string, count: int}>
     *     },
     *     ai: array{
     *         total_requests: int,
     *         total_tokens: int,
     *         total_cost_usd: float,
     *         period_days: int,
     *         period_requests: int,
     *         period_cost_usd: float,
     *         by_feature: list<array{feature: string, label: string, requests: int, tokens: int, cost_usd: float}>,
     *         daily: list<array{date: string, requests: int, cost_usd: float}>,
     *         top_users: list<array{name: string, email: string, requests: int, cost_usd: float}>
     *     }
     * }
     */
    public function snapshot(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, fn (): array => [
            'trips' => $this->tripAnalytics(),
            'ai' => $this->aiAnalytics(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tripAnalytics(): array
    {
        $empty = [
            'total' => 0,
            'vacation' => 0,
            'road' => 0,
            'domestic' => 0,
            'international' => 0,
            'by_month' => $this->emptyMonths(),
            'top_destinations' => [],
        ];

        try {
            /** @var Collection<int, Trip> $trips */
            $trips = Trip::query()
                ->active()
                ->get(['type', 'trip_scope', 'destination', 'created_at']);
        } catch (Throwable $exception) {
            Log::warning('Admin analytics could not query trips.', ['message' => $exception->getMessage()]);

            return $empty;
        }

        $months = collect($this->emptyMonths())->keyBy('label')->map(fn (array $row): int => 0);

        foreach ($trips as $trip) {
            $label = $trip->created_at?->format('M Y');

            if ($label !== null && $months->has($label)) {
                $months[$label] = $months[$label] + 1;
            }
        }

        $destinations = $trips
            ->map(fn (Trip $trip): ?string => Trip::normalizeLocation($trip->getAttribute('destination'))['label'] ?? null)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(self::TOP_LIMIT)
            ->map(fn (int $count, string $label): array => ['label' => $label, 'count' => $count])
            ->values()
            ->all();

        return [
            'total' => $trips->count(),
            'vacation' => $trips->where('type', TripType::Vacation)->count(),
            'road' => $trips->where('type', TripType::Road)->count(),
            'domestic' => $trips->where('trip_scope', TripScope::Domestic)->count(),
            'international' => $trips->where('trip_scope', TripScope::International)->count(),
            'by_month' => $months->map(fn (int $count, string $label): array => [
                'label' => $label,
                'count' => $count,
            ])->values()->all(),
            'top_destinations' => $destinations,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aiAnalytics(): array
    {
        $start = now()->subDays(self::DAYS - 1)->startOfDay();

        $empty = [
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost_usd' => 0.0,
            'period_days' => self::DAYS,
            'period_requests' => 0,
            'period_cost_usd' => 0.0,
            'by_feature' => [],
            'daily' => $this->emptyDays($start)->values()->all(),
            'top_users' => [],
        ];

        try {
            $totalRequests = AiUsageLog::query()->count();
            $totalTokens = (int) AiUsageLog::query()->sum('total_tokens');
            $totalCost = (float) AiUsageLog::query()->sum('cost_usd');

            /** @var Collection<int, AiUsageLog> $logs */
            $logs = AiUsageLog::query()
                ->where('created_at', '>=', $start)
                ->get(['user_id', 'feature', 'total_tokens', 'cost_usd', 'created_at']);
        } catch (Throwable $exception) {
            Log::warning('Admin analytics could not query AI usage.', ['message' => $exception->getMessage()]);

            return $empty;
        }

        $daily = $this->emptyDays($start);

        foreach ($logs->groupBy(fn (AiUsageLog $log): string => $log->created_at?->toDateString() ?? '') as $date => $group) {
            if ($daily->has($date)) {
                $daily[$date] = [
                    'date' => $date,
                    'requests' => $group->count(),
                    'cost_usd' => round((float) $group->sum('cost_usd'), 4),
                ];
            }
        }

        return [
            'total_requests' => $totalRequests,
            'total_tokens' => $totalTokens,
            'total_cost_usd' => round($totalCost, 4),
            'period_days' => self::DAYS,
            'period_requests' => $logs->count(),
            'period_cost_usd' => round((float) $logs->sum('cost_usd'), 4),
            'by_feature' => $this->byFeature($logs),
            'daily' => $daily->values()->all(),
            'top_users' => $this->topUsers($logs),
        ];
    }

    /**
     * @param  Collection<int, AiUsageLog>  $logs
     * @return list<array{feature: string, label: string, requests: int, tokens: int, cost_usd: float}>
     */
    private function byFeature(Collection $logs): array
    {
        return $logs
            ->groupBy('feature')
            ->map(function (Collection $group, string $feature): array {
                return [
                    'feature' => $feature,
                    'label' => (AiUsageFeature::tryFrom($feature) ?? AiUsageFeature::Other)->label(),
                    'requests' => $group->count(),
                    'tokens' => (int) $group->sum('total_tokens'),
                    'cost_usd' => round((float) $group->sum('cost_usd'), 4),
                ];
            })
            ->sortByDesc('requests')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AiUsageLog>  $logs
     * @return list<array{name: string, email: string, requests: int, cost_usd: float}>
     */
    private function topUsers(Collection $logs): array
    {
        $top = $logs
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->map(fn (Collection $group): array => [
                'requests' => $group->count(),
                'cost_usd' => round((float) $group->sum('cost_usd'), 4),
            ])
            ->sortByDesc('requests')
            ->take(5);

        $users = User::query()->whereIn('id', $top->keys())->get()->keyBy('id');

        return $top
            ->map(fn (array $row, int|string $userId): array => [
                'name' => $users->get($userId)?->name ?? 'Deleted user',
                'email' => $users->get($userId)?->email ?? '',
                ...$row,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    private function emptyMonths(): array
    {
        return collect(range(self::MONTHS - 1, 0))
            ->map(fn (int $ago): array => [
                'label' => now()->startOfMonth()->subMonths($ago)->format('M Y'),
                'count' => 0,
            ])
            ->all();
    }

    /**
     * @return Collection<string, array{date: string, requests: int, cost_usd: float}>
     */
    private function emptyDays(CarbonInterface $start): Collection
    {
        return collect(range(0, self::DAYS - 1))
            ->mapWithKeys(function (int $offset) use ($start): array {
                $date = $start->copy()->addDays($offset)->toDateString();

                return [$date => ['date' => $date, 'requests' => 0, 'cost_usd' => 0.0]];
            });
    }
}
