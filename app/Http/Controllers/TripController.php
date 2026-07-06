<?php

namespace App\Http\Controllers;

use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->string('filter', 'all')->toString();

        $query = Trip::query()
            ->forUser($request->user()->id)
            ->orderByDesc('created_at');

        $query = match ($filter) {
            'favorites' => $query->favorites()->active(),
            'archived' => $query->archived(),
            default => $query->active(),
        };

        $trips = $query->get()->map->toFrontend();

        return Inertia::render('Trips/Index', [
            'trips' => $trips,
            'filter' => $filter,
            'counts' => $this->tripCounts($request->user()->id),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Trips/Create', [
            'tripTypes' => $this->tripTypeOptions(),
            'tripStatuses' => $this->tripStatusOptions(),
        ]);
    }

    public function store(StoreTripRequest $request): RedirectResponse
    {
        $trip = Trip::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'status' => $request->enum('status', TripStatus::class) ?? TripStatus::Draft,
            'is_favorite' => false,
            'itinerary' => ['days' => [], 'summary' => ''],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trip created.')]);

        return to_route('trips.show', $trip);
    }

    public function show(Trip $trip): Response
    {
        $this->authorize('view', $trip);

        return Inertia::render('Trips/Show', [
            'trip' => $trip->toFrontend(),
        ]);
    }

    public function edit(Trip $trip): Response
    {
        $this->authorize('update', $trip);

        return Inertia::render('Trips/Edit', [
            'trip' => $trip->toFrontend(),
            'tripTypes' => $this->tripTypeOptions(),
            'tripStatuses' => $this->tripStatusOptions(),
        ]);
    }

    public function update(UpdateTripRequest $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $trip->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trip updated.')]);

        return to_route('trips.show', $trip);
    }

    public function destroy(Trip $trip): RedirectResponse
    {
        $this->authorize('delete', $trip);

        $trip->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trip deleted.')]);

        return to_route('trips.index');
    }

    public function toggleFavorite(Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $trip->update([
            'is_favorite' => ! $trip->is_favorite,
        ]);

        return back();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function tripTypeOptions(): array
    {
        return collect(TripType::cases())
            ->map(fn (TripType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function tripStatusOptions(): array
    {
        return collect(TripStatus::cases())
            ->map(fn (TripStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{all: int, favorites: int, archived: int}
     */
    protected function tripCounts(int $userId): array
    {
        $base = Trip::query()->forUser($userId);

        return [
            'all' => (clone $base)->active()->count(),
            'favorites' => (clone $base)->favorites()->active()->count(),
            'archived' => (clone $base)->archived()->count(),
        ];
    }
}
