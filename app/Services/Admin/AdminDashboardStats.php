<?php

namespace App\Services\Admin;

use App\Enums\TripType;
use App\Enums\UserRole;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminDashboardStats
{
    /**
     * @return array{
     *     users: array{total: int, admins: int},
     *     trips: array{total: int, vacation: int, road: int},
     *     ai_requests: array{total: int, chat_replies: int, itineraries: int}
     * }
     */
    public function snapshot(): array
    {
        return Cache::remember('admin:dashboard:stats', 60, fn (): array => $this->compute());
    }

    /**
     * @return array{
     *     users: array{total: int, admins: int},
     *     trips: array{total: int, vacation: int, road: int},
     *     ai_requests: array{total: int, chat_replies: int, itineraries: int}
     * }
     */
    private function compute(): array
    {
        $users = [
            'total' => User::query()->count(),
            'admins' => User::query()
                ->whereIn('role', [UserRole::Admin, UserRole::SuperAdmin])
                ->count(),
        ];

        $trips = $this->tripStats();
        $chatReplies = $this->countAssistantChatReplies();
        $itineraries = $this->countGeneratedItineraries();

        return [
            'users' => $users,
            'trips' => $trips,
            'ai_requests' => [
                'total' => $chatReplies + $itineraries,
                'chat_replies' => $chatReplies,
                'itineraries' => $itineraries,
            ],
        ];
    }

    /**
     * @return array{total: int, vacation: int, road: int}
     */
    private function tripStats(): array
    {
        return $this->withMongo(fn (): array => [
            'total' => Trip::query()->active()->count(),
            'vacation' => Trip::query()->active()->where('type', TripType::Vacation->value)->count(),
            'road' => Trip::query()->active()->where('type', TripType::Road->value)->count(),
        ], [
            'total' => 0,
            'vacation' => 0,
            'road' => 0,
        ]);
    }

    private function countAssistantChatReplies(): int
    {
        return $this->withMongo(fn (): int => Trip::query()
            ->active()
            ->whereNotNull('chat_messages')
            ->get(['chat_messages'])
            ->sum(function (Trip $trip): int {
                $messages = is_array($trip->chat_messages) ? $trip->chat_messages : [];

                return collect($messages)
                    ->where('role', 'assistant')
                    ->count();
            }), 0);
    }

    private function countGeneratedItineraries(): int
    {
        return $this->withMongo(
            fn (): int => Trip::query()
                ->active()
                ->where('itinerary.days.0', 'exists', true)
                ->count(),
            0,
        );
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @param  TValue  $fallback
     * @return TValue
     */
    private function withMongo(callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            Log::warning('Admin dashboard stats could not query MongoDB.', [
                'message' => $exception->getMessage(),
            ]);

            return $fallback;
        }
    }
}
