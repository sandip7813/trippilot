<?php

namespace App\Http\Controllers;

use App\Enums\TripJoinRequestStatus;
use App\Models\Trip;
use App\Models\TripJoinRequest;
use App\Services\Trips\OpenTripPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public-facing "Discover" listing and read-only overview for open trips.
 * Every response here must be built through OpenTripPresenter's whitelist,
 * never Trip::toFrontend(), since these routes are reachable by guests.
 */
class OpenTripController extends Controller
{
    public function index(Request $request, OpenTripPresenter $presenter): Response
    {
        $showPast = $request->boolean('past');

        $query = Trip::query()->published();
        $query = $showPast ? $query->pastTrips() : $query->upcomingOrOngoing();

        if ($category = $request->string('category')->toString()) {
            $query->where('open_trip.category', $category);
        }

        if ($destination = $request->string('destination')->toString()) {
            $query->where('destination.label', 'like', "%{$destination}%");
        }

        $trips = $query
            ->orderBy($showPast ? 'end_date' : 'start_date', $showPast ? 'desc' : 'asc')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Trip $trip): array => $presenter->overview($trip));

        return Inertia::render('OpenTrips/Index', [
            'trips' => $trips,
            'showPast' => $showPast,
            'filters' => $request->only(['category', 'destination']),
        ]);
    }

    public function show(Request $request, Trip $trip, OpenTripPresenter $presenter): Response
    {
        $this->authorize('viewPublic', $trip);

        $user = $request->user();
        $myJoinRequest = null;

        if ($user !== null) {
            $myJoinRequest = TripJoinRequest::query()
                ->where('trip_id', (string) $trip->id)
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->first();
        }

        return Inertia::render('OpenTrips/Show', [
            'trip' => $presenter->overview($trip),
            'isOwner' => $user?->id === $trip->user_id,
            'isMember' => $user !== null && $trip->isMember($user),
            'myJoinRequest' => $myJoinRequest === null ? null : [
                'id' => (string) $myJoinRequest->id,
                'status' => $myJoinRequest->status->value,
                'is_pending' => $myJoinRequest->status === TripJoinRequestStatus::Pending,
            ],
        ]);
    }
}
