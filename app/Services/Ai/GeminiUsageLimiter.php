<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Cost guardrail for Gemini-backed features (itinerary generation, trip
 * chat, assistant chat). Tracks how many requests a user has made today
 * against a configurable daily cap, independent of the short per-minute
 * throttle already applied at the route level.
 */
class GeminiUsageLimiter
{
    public function dailyLimit(): int
    {
        return (int) config('integrations.ai.daily_limit_per_user', 50);
    }

    public function usedToday(User $user): int
    {
        return (int) Cache::get($this->cacheKey($user), 0);
    }

    public function remaining(User $user): ?int
    {
        $limit = $this->dailyLimit();

        if ($limit <= 0) {
            return null;
        }

        return max(0, $limit - $this->usedToday($user));
    }

    public function hasRemaining(User $user): bool
    {
        $limit = $this->dailyLimit();

        return $limit <= 0 || $this->usedToday($user) < $limit;
    }

    /**
     * Record one Gemini request against the user's daily count.
     */
    public function increment(User $user): void
    {
        $key = $this->cacheKey($user);

        Cache::add($key, 0, now()->endOfDay());
        Cache::increment($key);
    }

    private function cacheKey(User $user): string
    {
        return sprintf('gemini_usage:%d:%s', $user->id, now()->toDateString());
    }
}
