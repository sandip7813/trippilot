<?php

namespace App\Http\Controllers;

use App\Actions\Trips\SyncTripCoverImage;
use App\Enums\DrivingPace;
use App\Enums\FoodPreference;
use App\Enums\FuelType;
use App\Enums\TripScope;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Enums\VehicleClass;
use App\Exceptions\AiGenerationException;
use App\Exceptions\RoadTripException;
use App\Http\Requests\StoreRoadTripRequest;
use App\Http\Requests\UpdateRoadTripRequest;
use App\Models\Trip;
use App\Services\RoadTrips\RoadTripAmenitiesService;
use App\Services\RoadTrips\RoadTripBreakSuggestionService;
use App\Services\RoadTrips\RoadTripRouteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoadTripController extends Controller
{
    public function index(Request $request): Response
    {
        $trips = Trip::query()
            ->forUser($request->user()->id)
            ->road()
            ->active()
            ->orderByDesc('created_at')
            ->get()
            ->map->toFrontend();

        return Inertia::render('RoadTrips/Index', [
            'trips' => $trips,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('RoadTrips/Create', [
            ...$this->formOptions(),
            'defaultOrigin' => $request->user()->homeCityLocation(),
        ]);
    }

    public function store(
        StoreRoadTripRequest $request,
        SyncTripCoverImage $syncTripCoverImage,
        RoadTripRouteService $routeService,
    ): RedirectResponse {
        $validated = $request->validated();
        $locations = $this->prepareTripLocations($validated);

        $trip = Trip::query()->create([
            ...$validated,
            ...$locations,
            'type' => TripType::Road,
            'user_id' => $request->user()->id,
            'status' => TripStatus::Draft,
            'is_favorite' => false,
            'itinerary' => Trip::emptyItinerary(),
            'stops' => [],
            'suggested_breaks' => [],
            'road_profile' => $validated['road_profile'],
        ]);

        $syncTripCoverImage($trip);

        try {
            $routeService->computeAndStore($trip->fresh());
            $toastType = 'success';
            $toastMessage = __('Road trip created. Route calculated — suggest breaks when ready.');
        } catch (RoadTripException $exception) {
            $toastType = 'warning';
            $toastMessage = __('Road trip created, but the route could not be calculated yet.');
        }

        Inertia::flash('toast', [
            'type' => $toastType,
            'message' => $toastMessage,
        ]);

        return to_route('road-trips.show', $trip);
    }

    public function show(Trip $road_trip): Response
    {
        $this->authorize('view', $road_trip);
        $this->ensureRoadTrip($road_trip);

        return Inertia::render('RoadTrips/Show', [
            'trip' => $road_trip->toFrontend(),
            ...$this->formOptions(),
            'mapsConfigured' => filled(config('integrations.maps.drivers.geoapify.api_key')),
            'aiConfigured' => filled(config('integrations.ai.drivers.gemini.api_key')),
            'amenityLayers' => array_keys(RoadTripAmenitiesService::LAYER_CATEGORIES),
        ]);
    }

    public function edit(Trip $road_trip): Response
    {
        $this->authorize('update', $road_trip);
        $this->ensureRoadTrip($road_trip);

        return Inertia::render('RoadTrips/Edit', [
            'trip' => $road_trip->toFrontend(),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateRoadTripRequest $request, Trip $road_trip, SyncTripCoverImage $syncTripCoverImage): RedirectResponse
    {
        $this->authorize('update', $road_trip);
        $this->ensureRoadTrip($road_trip);

        $validated = $request->validated();
        $previousDestinationLabel = Trip::normalizeLocation($road_trip->getAttribute('destination'))['label'] ?? null;

        if (array_key_exists('origin', $validated) || array_key_exists('destination', $validated)) {
            $locations = $this->prepareTripLocations([
                'origin' => $validated['origin'] ?? Trip::normalizeLocation($road_trip->getAttribute('origin')),
                'destination' => $validated['destination'] ?? Trip::normalizeLocation($road_trip->getAttribute('destination')),
            ]);

            $validated = [...$validated, ...$locations];
        }

        $road_trip->update($validated);

        $newDestinationLabel = Trip::normalizeLocation($road_trip->getAttribute('destination'))['label'] ?? null;

        if (($previousDestinationLabel ?? '') !== ($newDestinationLabel ?? '')) {
            $syncTripCoverImage($road_trip->fresh());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Road trip updated.')]);

        return to_route('road-trips.show', $road_trip);
    }

    public function computeRoute(Trip $trip, RoadTripRouteService $routeService): RedirectResponse
    {
        $this->authorize('update', $trip);
        $this->ensureRoadTrip($trip);

        try {
            $routeService->computeAndStore($trip->fresh());
        } catch (RoadTripException $exception) {
            return back()->withErrors(['route' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Route updated.')]);

        return back();
    }

    public function suggestBreaks(Trip $trip, RoadTripBreakSuggestionService $breakSuggestionService): RedirectResponse
    {
        $this->authorize('update', $trip);
        $this->ensureRoadTrip($trip);

        if (! is_array($trip->route) || ($trip->route['polyline'] ?? []) === []) {
            return back()->withErrors(['route' => __('Calculate the route before suggesting breaks.')]);
        }

        if (! filled(config('integrations.ai.drivers.gemini.api_key'))) {
            return back()->withErrors(['ai' => __('AI is not configured. Add GEMINI_API_KEY to your environment.')]);
        }

        try {
            $breaks = $breakSuggestionService->suggest($trip->fresh());
        } catch (AiGenerationException $exception) {
            return back()->withErrors(['ai' => $exception->getMessage()]);
        }

        $trip->update(['suggested_breaks' => $breaks]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $breaks === []
                ? __('No break suggestions were found along this route.')
                : __('Break suggestions ready.'),
        ]);

        return back();
    }

    public function amenities(Request $request, Trip $trip, RoadTripAmenitiesService $amenitiesService): RedirectResponse
    {
        $this->authorize('view', $trip);
        $this->ensureRoadTrip($trip);

        $layer = (string) $request->string('layer', 'fuel');

        if (! is_array($trip->route) || ($trip->route['polyline'] ?? []) === []) {
            return back()->withErrors(['route' => __('Calculate the route before loading amenities.')]);
        }

        $places = $amenitiesService->fetchForTrip($trip, $layer);
        $cache = is_array($trip->amenities_cache) ? $trip->amenities_cache : [];
        $cache[$layer] = [
            'places' => $places,
            'fetched_at' => now()->toIso8601String(),
        ];

        $trip->update(['amenities_cache' => $cache]);

        return back();
    }

    public function acceptBreak(Trip $trip, Request $request): RedirectResponse
    {
        $this->authorize('update', $trip);
        $this->ensureRoadTrip($trip);

        $breakId = (string) $request->string('break_id');

        /** @var list<array<string, mixed>> $suggested */
        $suggested = is_array($trip->suggested_breaks) ? $trip->suggested_breaks : [];
        $match = collect($suggested)->firstWhere('id', $breakId);

        if (! is_array($match)) {
            return back()->withErrors(['break' => __('Break suggestion not found.')]);
        }

        /** @var list<array<string, mixed>> $stops */
        $stops = is_array($trip->stops) ? $trip->stops : [];

        $stops[] = [
            'label' => (string) ($match['label'] ?? $match['title']),
            'lat' => (float) $match['lat'],
            'lng' => (float) $match['lng'],
            'place_id' => $match['place_id'] ?? null,
            'kind' => (string) ($match['kind'] ?? 'break'),
            'notes' => (string) ($match['reason'] ?? ''),
            'source' => 'ai_suggested',
        ];

        $trip->update(['stops' => $stops]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stop added to your trip.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'vehicleClasses' => VehicleClass::options(),
            'fuelTypes' => FuelType::options(),
            'drivingPaces' => DrivingPace::options(),
            'foodPreferences' => FoodPreference::options(),
        ];
    }

    /**
     * @param  array{origin?: array<string, mixed>|null, destination?: array<string, mixed>|null}  $validated
     * @return array{origin: array<string, mixed>|null, destination: array<string, mixed>|null, trip_scope: TripScope|null}
     */
    private function prepareTripLocations(array $validated): array
    {
        $origin = Trip::normalizeLocation($validated['origin'] ?? null);
        $destination = Trip::normalizeLocation($validated['destination'] ?? null);

        return [
            'origin' => $origin,
            'destination' => $destination,
            'trip_scope' => Trip::resolveTripScope($origin, $destination),
        ];
    }

    private function ensureRoadTrip(Trip $trip): void
    {
        if (! $trip->isRoadTrip()) {
            abort(404);
        }
    }
}
