<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;
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
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map->toFrontend();

        $tripCount = Trip::query()->forUser($userId)->active()->count();
        $roadTripCount = Trip::query()
            ->forUser($userId)
            ->active()
            ->where('type', 'road')
            ->count();

        $upcomingTrip = Trip::query()
            ->forUser($userId)
            ->active()
            ->whereNotNull('start_date')
            ->where('start_date', '>=', now()->toDateString())
            ->orderBy('start_date')
            ->first();

        return Inertia::render('Dashboard', [
            'stats' => [
                'trips' => $tripCount,
                'road_trips' => $roadTripCount,
                'upcoming' => $upcomingTrip?->start_date?->format('M j, Y'),
            ],
            'recentTrips' => $recentTrips,
        ]);
    }
}
