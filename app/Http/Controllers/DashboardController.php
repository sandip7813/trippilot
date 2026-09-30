<?php

namespace App\Http\Controllers;

use App\Enums\TripPhase;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $userId = $request->user()->id;

        $recentTrips = Trip::query()
            ->forUser($userId)
            ->active()
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get()
            ->map->toFrontend();

        $tripCount = Trip::query()->forUser($userId)->active()->count();
        $roadTripCount = Trip::query()
            ->forUser($userId)
            ->active()
            ->where('type', 'road')
            ->count();
        $favoriteCount = Trip::query()->forUser($userId)->active()->favorites()->count();

        $invitedTrips = Trip::query()
            ->sharedWithUser($userId)
            ->active()
            ->orderByDesc('created_at')
            ->limit(4)
            ->get()
            ->map->toFrontend();

        $invitedTripCount = Trip::query()->sharedWithUser($userId)->active()->count();

        $phaseCounts = collect(TripPhase::cases())
            ->mapWithKeys(fn (TripPhase $phase): array => [
                $phase->value => Trip::query()->forUser($userId)->active()->inPhase($phase)->count(),
            ]);

        $nextTrip = $this->nextTrip($userId);

        return Inertia::render('Dashboard', [
            'stats' => [
                'trips' => $tripCount,
                'road_trips' => $roadTripCount,
                'favorites' => $favoriteCount,
                'upcoming' => $nextTrip?->start_date?->toDateString(),
                'invited' => $invitedTripCount,
            ],
            'phaseCounts' => $phaseCounts,
            'nextTrip' => $nextTrip?->toFrontend(),
            'recentTrips' => $recentTrips,
            'invitedTrips' => $invitedTrips,
        ]);
    }

    /**
     * The trip currently under way, otherwise the soonest dated departure the
     * user owns or has joined.
     */
    private function nextTrip(int $userId): ?Trip
    {
        $ongoing = Trip::query()
            ->forUserOrCollaborator($userId)
            ->active()
            ->inPhase(TripPhase::Ongoing)
            ->orderBy('start_date')
            ->first();

        return $ongoing ?? Trip::query()
            ->forUserOrCollaborator($userId)
            ->active()
            ->whereNotNull('start_date')
            ->where('start_date', '>=', Carbon::today())
            ->orderBy('start_date')
            ->first();
    }
}
