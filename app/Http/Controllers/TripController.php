<?php

namespace App\Http\Controllers;

use App\Actions\Trips\GenerateTripItinerary;
use App\Actions\Trips\SendTripChatMessage;
use App\Actions\Trips\SyncTripCoverImage;
use App\Enums\TravelStyle;
use App\Enums\TripRouteMode;
use App\Enums\TripScope;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Exceptions\AiGenerationException;
use App\Http\Requests\ChatTripRequest;
use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Http\Requests\UploadTripCoverImageRequest;
use App\Models\Trip;
use App\Services\Trains\TripTrainHaltsService;
use App\Services\Trains\TripTrainService;
use App\Services\Trips\TripCoverImageService;
use App\Services\Weather\TripWeatherService;
use Illuminate\Http\JsonResponse;
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

    public function create(Request $request): Response
    {
        return Inertia::render('Trips/Create', [
            'tripTypes' => $this->tripTypeOptions(),
            'tripStatuses' => $this->tripStatusOptions(),
            'travelStyles' => $this->travelStyleOptions(),
            'defaultOrigin' => $request->user()->homeCityLocation(),
        ]);
    }

    public function store(StoreTripRequest $request, SyncTripCoverImage $syncTripCoverImage): RedirectResponse
    {
        $validated = $request->validated();

        $locations = $this->prepareTripLocations($validated);

        $trip = Trip::query()->create([
            ...$validated,
            ...$locations,
            'user_id' => $request->user()->id,
            'status' => $request->enum('status', TripStatus::class) ?? TripStatus::Draft,
            'is_favorite' => false,
            'itinerary' => [
                'days' => [],
                'summary' => '',
                'packing_list' => [],
                'budget_breakdown' => [],
            ],
        ]);

        $syncTripCoverImage($trip, onlyIfMissing: true);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Trip created. Your destination cover will appear shortly.'),
        ]);

        return to_route('trips.show', $trip);
    }

    public function show(Trip $trip, TripWeatherService $tripWeather, TripTrainService $tripTrains): Response
    {
        $this->authorize('view', $trip);

        return Inertia::render('Trips/Show', [
            'trip' => $trip->toFrontend(),
            'aiConfigured' => filled(config('integrations.ai.drivers.gemini.api_key')),
            'weather' => $tripWeather->forTrip($trip),
            'trainTimings' => $tripTrains->forTrip($trip),
        ]);
    }

    public function trainHalts(Trip $trip, string $trainNumber, Request $request, TripTrainHaltsService $tripTrainHalts): JsonResponse
    {
        $this->authorize('view', $trip);

        $validated = $request->validate([
            'from' => ['required', 'string', 'min:2', 'max:6'],
            'to' => ['required', 'string', 'min:2', 'max:6'],
            'date' => ['nullable', 'date'],
            'from_sequence' => ['nullable', 'integer', 'min:1'],
            'to_sequence' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json(
            $tripTrainHalts->forSegment($trainNumber, $validated),
        );
    }

    public function edit(Trip $trip): Response
    {
        $this->authorize('update', $trip);

        return Inertia::render('Trips/Edit', [
            'trip' => $trip->toFrontend(),
            'tripTypes' => $this->tripTypeOptions(),
            'tripStatuses' => $this->tripStatusOptions(),
            'travelStyles' => $this->travelStyleOptions(),
        ]);
    }

    public function update(UpdateTripRequest $request, Trip $trip, SyncTripCoverImage $syncTripCoverImage): RedirectResponse
    {
        $this->authorize('update', $trip);

        $validated = $request->validated();
        $previousDestinationLabel = Trip::normalizeLocation($trip->getAttribute('destination'))['label'] ?? null;

        if (array_key_exists('origin', $validated) || array_key_exists('destination', $validated)) {
            $locations = $this->prepareTripLocations([
                'origin' => $validated['origin'] ?? Trip::normalizeLocation($trip->getAttribute('origin')),
                'destination' => $validated['destination'] ?? Trip::normalizeLocation($trip->getAttribute('destination')),
            ]);

            $validated = [
                ...$validated,
                ...$locations,
            ];
        }

        $itineraryCleared = false;

        if ($trip->hasGeneratedItinerary() && $trip->materialAttributesDiffer($validated)) {
            $validated['itinerary'] = Trip::emptyItinerary();
            $validated['status'] = TripStatus::Draft;
            $itineraryCleared = true;
        }

        $trip->update($validated);

        $newDestinationLabel = Trip::normalizeLocation($trip->getAttribute('destination'))['label'] ?? null;
        $destinationChanged = ($previousDestinationLabel ?? '') !== ($newDestinationLabel ?? '');

        if ($destinationChanged) {
            $trip->update([
                'cover_image_path' => null,
                'cover_image_thumb_path' => null,
                'cover_image_source' => null,
                'cover_image_source_index' => null,
                'cover_image_ref' => null,
                'cover_image_tried_refs' => [],
                'cover_image_exhausted' => false,
                'cover_image_attribution' => null,
            ]);

            $syncTripCoverImage($trip->fresh(), onlyIfMissing: true);
        }

        if ($itineraryCleared) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('Trip updated. Your previous AI itinerary was removed — generate a new one when ready.'),
            ]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Trip updated.')]);
        }

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

    public function syncCover(Trip $trip, SyncTripCoverImage $syncTripCoverImage): RedirectResponse
    {
        $this->authorize('update', $trip);

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

    public function generateItinerary(Trip $trip, GenerateTripItinerary $generateItinerary): RedirectResponse
    {
        $this->authorize('generateItinerary', $trip);

        $destination = Trip::normalizeLocation($trip->getAttribute('destination'));

        if ($destination === null || $destination['label'] === null || $destination['label'] === '') {
            return back()->withErrors([
                'destination' => __('Set a destination before generating an itinerary.'),
            ]);
        }

        if (! filled(config('integrations.ai.drivers.gemini.api_key'))) {
            return back()->withErrors([
                'ai' => __('AI generation is not configured. Add GEMINI_API_KEY to your environment.'),
            ]);
        }

        try {
            $generateItinerary($trip);
        } catch (AiGenerationException $exception) {
            return back()->withErrors([
                'ai' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Itinerary generated!')]);

        return to_route('trips.show', $trip);
    }

    public function chat(ChatTripRequest $request, Trip $trip, SendTripChatMessage $sendTripChatMessage): RedirectResponse
    {
        $this->authorize('chat', $trip);

        if (! filled(config('integrations.ai.drivers.gemini.api_key'))) {
            return back()->withErrors([
                'ai' => __('AI chat is not configured. Add GEMINI_API_KEY to your environment.'),
            ]);
        }

        try {
            $result = $sendTripChatMessage($trip, $request->validated('message'));
        } catch (AiGenerationException $exception) {
            return back()->withErrors([
                'chat' => $exception->getMessage(),
            ]);
        }

        if ($result['patch_applied']) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Trip updated based on your request.'),
            ]);
        }

        return back();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function tripTypeOptions(): array
    {
        return array_map(
            fn (TripType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            TripType::cases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function tripStatusOptions(): array
    {
        return array_map(
            fn (TripStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            TripStatus::cases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function travelStyleOptions(): array
    {
        return array_map(
            fn (TravelStyle $style): array => [
                'value' => $style->value,
                'label' => $style->label(),
            ],
            TravelStyle::cases(),
        );
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
    protected function prepareTripLocations(array $validated): array
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
