<?php

namespace App\Http\Controllers;

use App\Actions\Trips\SyncTripCoverImage;
use App\Enums\DrivingPace;
use App\Enums\FoodPreference;
use App\Enums\FuelType;
use App\Enums\TripRouteMode;
use App\Enums\TripScope;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Enums\VehicleClass;
use App\Exceptions\AiGenerationException;
use App\Exceptions\RoadTripException;
use App\Http\Requests\StoreRoadTripRequest;
use App\Http\Requests\UpdateRoadTripRequest;
use App\Http\Requests\UploadTripCoverImageRequest;
use App\Models\Trip;
use App\Services\RoadTrips\RoadTripAmenitiesService;
use App\Services\RoadTrips\RoadTripBreakSuggestionService;
use App\Services\RoadTrips\RoadTripRouteService;
use App\Services\Trips\TripAiContextBuilder;
use App\Services\Trips\TripCoverImageService;
use App\Services\Weather\TripWeatherService;
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

        $syncTripCoverImage($trip, onlyIfMissing: true);

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

    public function show(Trip $road_trip, TripWeatherService $tripWeather, TripAiContextBuilder $tripAiContext): Response
    {
        $this->authorize('view', $road_trip);
        $this->ensureRoadTrip($road_trip);

        return Inertia::render('RoadTrips/Show', [
            'trip' => $road_trip->toFrontend(),
            ...$this->formOptions(),
            'mapsConfigured' => filled(config('integrations.maps.drivers.geoapify.api_key')),
            'aiConfigured' => filled(config('integrations.ai.drivers.gemini.api_key')),
            'ragCoverage' => $tripAiContext->ragCoverage($road_trip),
            'amenityLayers' => app(RoadTripAmenitiesService::class)->layersForTrip($road_trip),
            'weather' => $tripWeather->forTrip($road_trip),
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

    public function update(
        UpdateRoadTripRequest $request,
        Trip $road_trip,
        SyncTripCoverImage $syncTripCoverImage,
        RoadTripRouteService $routeService,
    ): RedirectResponse {
        $this->authorize('update', $road_trip);
        $this->ensureRoadTrip($road_trip);

        $validated = $request->validated();
        $previousDestinationLabel = Trip::normalizeLocation($road_trip->getAttribute('destination'))['label'] ?? null;
        $shouldRecomputeRoute = $road_trip->materialAttributesDiffer($validated);

        if ($this->hasLocationInputs($validated)) {
            $locations = $this->prepareTripLocations([
                'origin' => $validated['origin'] ?? Trip::normalizeLocation($road_trip->getAttribute('origin')),
                'destination' => $validated['destination'] ?? Trip::normalizeLocation($road_trip->getAttribute('destination')),
                'waypoints' => $validated['waypoints'] ?? $road_trip->getAttribute('waypoints'),
                'route_mode' => $validated['route_mode'] ?? $road_trip->route_mode?->value,
                'returns_to_origin' => $validated['returns_to_origin'] ?? $road_trip->returns_to_origin,
            ]);

            $validated = [...$validated, ...$locations];
            $shouldRecomputeRoute = $road_trip->materialAttributesDiffer($validated);
        }

        $road_trip->update($validated);

        $newDestinationLabel = Trip::normalizeLocation($road_trip->getAttribute('destination'))['label'] ?? null;

        if (($previousDestinationLabel ?? '') !== ($newDestinationLabel ?? '')) {
            $road_trip->update([
                'cover_image_path' => null,
                'cover_image_thumb_path' => null,
                'cover_image_source' => null,
                'cover_image_source_index' => null,
                'cover_image_ref' => null,
                'cover_image_tried_refs' => [],
                'cover_image_exhausted' => false,
                'cover_image_attribution' => null,
            ]);

            $syncTripCoverImage($road_trip->fresh(), onlyIfMissing: true);
        }

        if ($shouldRecomputeRoute) {
            $road_trip->update([
                'amenities_cache' => [],
                'suggested_breaks' => [],
            ]);

            try {
                $routeService->computeAndStore($road_trip->fresh());
            } catch (RoadTripException) {
                $road_trip->update(['route' => null]);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Road trip updated.')]);

        return to_route('road-trips.show', $road_trip);
    }

    public function syncCover(Trip $trip, SyncTripCoverImage $syncTripCoverImage): RedirectResponse
    {
        $this->authorize('update', $trip);
        $this->ensureRoadTrip($trip);

        $syncTripCoverImage($trip, onlyIfMissing: false);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Looking for another photo from a different source.'),
        ]);

        return back();
    }

    public function uploadCover(
        UploadTripCoverImageRequest $request,
        Trip $trip,
        TripCoverImageService $coverImageService,
    ): RedirectResponse {
        $this->authorize('update', $trip);
        $this->ensureRoadTrip($trip);

        $path = $coverImageService->storeUpload($trip, $request->file('cover'));

        if ($path === null) {
            return back()->withErrors([
                'cover' => __('The cover image could not be uploaded.'),
            ]);
        }

        $trip->increment('cover_image_version');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Cover image uploaded.'),
        ]);

        return back();
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

        $route = $trip->routeData();

        if (! is_array($route) || ($route['polyline'] ?? []) === []) {
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

        if (! in_array($layer, $amenitiesService->layersForTrip($trip), true)) {
            return back()->withErrors([
                'amenity' => __('This amenity type is not relevant for your vehicle.'),
            ]);
        }

        $route = $trip->routeData();

        if (! is_array($route) || ($route['polyline'] ?? []) === []) {
            return back()->withErrors(['route' => __('Calculate the route before loading amenities.')]);
        }

        $places = $amenitiesService->fetchForTrip($trip, $layer);
        $cache = Trip::coerceStructuredArray($trip->getAttribute('amenities_cache')) ?? [];
        $cache[$layer] = [
            'places' => $places,
            'fetched_at' => now()->toIso8601String(),
        ];

        $trip->update(['amenities_cache' => $cache]);

        Inertia::flash('amenityLayer', $layer);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':layer loaded on the map.', [
                'layer' => __(RoadTripAmenitiesService::LAYER_LABELS[$layer] ?? $layer),
            ]),
        ]);

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
            'address' => isset($match['address']) ? (string) $match['address'] : null,
            'source' => 'ai_suggested',
        ];

        $trip->update(['stops' => $stops]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stop added to your trip.')]);

        return back();
    }

    public function removeStop(Trip $trip, Request $request): RedirectResponse
    {
        $this->authorize('update', $trip);
        $this->ensureRoadTrip($trip);

        $stopIndex = $request->integer('stop_index');

        if ($stopIndex < 0) {
            return back()->withErrors(['stop' => __('Stop not found.')]);
        }

        /** @var list<array<string, mixed>> $stops */
        $stops = is_array($trip->stops) ? $trip->stops : [];

        if (! array_key_exists($stopIndex, $stops)) {
            return back()->withErrors(['stop' => __('Stop not found.')]);
        }

        array_splice($stops, $stopIndex, 1);

        $trip->update(['stops' => array_values($stops)]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stop removed.')]);

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
     * @param  array{origin?: array<string, mixed>|null, destination?: array<string, mixed>|null, waypoints?: list<array<string, mixed>>|null, route_mode?: string|null, returns_to_origin?: bool|null}  $validated
     * @return array{
     *     origin: array<string, mixed>|null,
     *     destination: array<string, mixed>|null,
     *     waypoints: list<array<string, mixed>>,
     *     route_mode: TripRouteMode,
     *     returns_to_origin: bool,
     *     trip_scope: TripScope|null,
     * }
     */
    private function prepareTripLocations(array $validated): array
    {
        $origin = Trip::normalizeLocation($validated['origin'] ?? null);
        $waypoints = Trip::normalizeWaypoints($validated['waypoints'] ?? null);
        $routeMode = TripRouteMode::tryFrom((string) ($validated['route_mode'] ?? ''))
            ?? (count($waypoints) >= 2 ? TripRouteMode::MultiCity : TripRouteMode::Simple);

        if ($routeMode === TripRouteMode::MultiCity) {
            $lastWaypoint = $waypoints[array_key_last($waypoints)] ?? null;
            $destination = is_array($lastWaypoint)
                ? ($lastWaypoint['location'] ?? null)
                : Trip::normalizeLocation($validated['destination'] ?? null);
        } else {
            $destination = Trip::normalizeLocation($validated['destination'] ?? null);
            $waypoints = [];
            $routeMode = TripRouteMode::Simple;
        }

        $returnsToOrigin = filter_var(
            $validated['returns_to_origin'] ?? true,
            FILTER_VALIDATE_BOOLEAN,
        );

        $locations = collect([$origin, ...collect($waypoints)->pluck('location')->all(), $destination])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->all();

        return [
            'origin' => $origin,
            'destination' => $destination,
            'waypoints' => $waypoints,
            'route_mode' => $routeMode,
            'returns_to_origin' => $returnsToOrigin,
            'trip_scope' => Trip::resolveTripScopeFromLocations($locations),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function hasLocationInputs(array $validated): bool
    {
        foreach (['origin', 'destination', 'waypoints', 'route_mode', 'returns_to_origin'] as $key) {
            if (array_key_exists($key, $validated)) {
                return true;
            }
        }

        return false;
    }

    private function ensureRoadTrip(Trip $trip): void
    {
        if (! $trip->isRoadTrip()) {
            abort(404);
        }
    }
}
