<?php

namespace App\Http\Controllers;

use App\Actions\Trips\DecideTripJoinRequest;
use App\Actions\Trips\SubmitTripJoinRequest;
use App\Actions\Trips\WithdrawTripJoinRequest;
use App\Http\Requests\SubmitTripJoinRequestRequest;
use App\Models\Trip;
use App\Models\TripJoinRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class TripJoinRequestController extends Controller
{
    /**
     * The owner's queue of join requests for this trip.
     */
    public function index(Trip $trip): Response
    {
        $this->authorize('manageJoinRequests', $trip);

        $requests = TripJoinRequest::query()
            ->where('trip_id', (string) $trip->id)
            ->orderByDesc('created_at')
            ->get();

        $requesters = User::query()->whereIn('id', $requests->pluck('user_id')->unique()->all())->get()->keyBy('id');

        return Inertia::render('Trips/JoinRequests', [
            'trip' => [
                'id' => (string) $trip->id,
                'title' => $trip->title,
                'max_group_size' => $trip->maxGroupSize(),
                'seats_left' => $trip->maxGroupSize() !== null
                    ? max(0, $trip->maxGroupSize() - $trip->acceptedMemberCount())
                    : null,
            ],
            'requests' => $this->requestsForFrontend($requests, $requesters),
        ]);
    }

    public function store(SubmitTripJoinRequestRequest $request, Trip $trip, SubmitTripJoinRequest $submitTripJoinRequest): RedirectResponse
    {
        try {
            $submitTripJoinRequest($trip, $request->user(), $request->validated());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['join' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request sent. The organizer will review it.')]);

        return back();
    }

    public function destroy(Trip $trip, TripJoinRequest $joinRequest, WithdrawTripJoinRequest $withdrawTripJoinRequest): RedirectResponse
    {
        abort_unless((string) $joinRequest->trip_id === (string) $trip->id, 404);

        try {
            $withdrawTripJoinRequest($joinRequest, request()->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['join' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request withdrawn.')]);

        return back();
    }

    public function accept(Trip $trip, TripJoinRequest $joinRequest, DecideTripJoinRequest $decideTripJoinRequest): RedirectResponse
    {
        $this->authorize('manageJoinRequests', $trip);
        abort_unless((string) $joinRequest->trip_id === (string) $trip->id, 404);

        try {
            $decideTripJoinRequest->accept($trip, $joinRequest, request()->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['join' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request accepted.')]);

        return back();
    }

    public function decline(Trip $trip, TripJoinRequest $joinRequest, DecideTripJoinRequest $decideTripJoinRequest): RedirectResponse
    {
        $this->authorize('manageJoinRequests', $trip);
        abort_unless((string) $joinRequest->trip_id === (string) $trip->id, 404);

        try {
            $decideTripJoinRequest->decline($trip, $joinRequest, request()->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['join' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request declined.')]);

        return back();
    }

    /**
     * @param  Collection<int, TripJoinRequest>  $requests
     * @param  Collection<int, User>  $requesters
     * @return list<array<string, mixed>>
     */
    private function requestsForFrontend(Collection $requests, Collection $requesters): array
    {
        return $requests->map(function (TripJoinRequest $request) use ($requesters): array {
            $requester = $requesters->get($request->user_id);

            return [
                'id' => (string) $request->id,
                'status' => $request->status->value,
                'status_label' => $request->status->label(),
                'travelers_count' => $request->travelers_count,
                'phone' => $request->status->value === 'accepted' ? $request->phone : null,
                'message' => $request->message,
                'requester_name' => $requester?->name ?? 'Deleted user',
                'created_at' => $request->created_at?->toIso8601String(),
            ];
        })->values()->all();
    }
}
