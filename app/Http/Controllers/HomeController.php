<?php

namespace App\Http\Controllers;

use App\Enums\TripPhase;
use App\Models\Trip;
use App\Models\User;
use App\Services\Trips\OpenTripPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public landing page. Everything guests see is built from published trips
 * through OpenTripPresenter's whitelist; the viewer's own trips are only
 * added when someone is signed in.
 */
class HomeController extends Controller
{
    /**
     * How many recently published trips feed the destination and category
     * aggregates — enough to be representative without scanning everything.
     */
    private const AGGREGATE_SAMPLE_SIZE = 300;

    public function __invoke(Request $request, OpenTripPresenter $presenter): Response
    {
        $user = $request->user();

        $openTrips = Trip::query()
            ->published()
            ->active()
            ->upcomingOrOngoing()
            ->orderBy('start_date')
            ->limit(6)
            ->get()
            ->map(fn (Trip $trip): array => $presenter->card($trip))
            ->values();

        $pastOpenTrips = Trip::query()
            ->published()
            ->active()
            ->pastTrips()
            ->orderByDesc('end_date')
            ->limit(4)
            ->get()
            ->map(fn (Trip $trip): array => $presenter->card($trip))
            ->values();

        $publishedSample = Trip::query()
            ->published()
            ->active()
            ->orderByDesc('published_at')
            ->limit(self::AGGREGATE_SAMPLE_SIZE)
            ->get();

        $destinations = $this->popularDestinations($publishedSample);

        return Inertia::render('Welcome', [
            'stats' => [
                'open_trips' => Trip::query()->published()->active()->upcomingOrOngoing()->count(),
                'destinations' => $destinations['total'],
                'travelers' => User::query()->count(),
                'trips_planned' => Trip::query()->count(),
            ],
            'openTrips' => $openTrips,
            'pastOpenTrips' => $pastOpenTrips,
            'destinations' => $destinations['top'],
            'categories' => $this->categoryCounts($publishedSample),
            'myTrips' => $user !== null ? $this->myTrips($user) : null,
        ]);
    }

    /**
     * Destinations ranked by how many published trips head there, each with
     * a cover photo borrowed from one of those trips.
     *
     * @param  Collection<int, Trip>  $trips
     * @return array{total: int, top: list<array{label: string, name: string, region: string|null, trip_count: int, cover_image_url: string|null}>}
     */
    private function popularDestinations(Collection $trips): array
    {
        $grouped = $trips
            ->map(fn (Trip $trip): array => [
                'label' => Trip::normalizeLocation($trip->getAttribute('destination'))['label'] ?? null,
                'cover' => $trip->coverImageThumbUrl(),
            ])
            ->filter(fn (array $entry): bool => filled($entry['label']))
            ->groupBy(fn (array $entry): string => Str::lower(trim((string) $entry['label'])));

        $top = $grouped
            ->map(function (Collection $entries): array {
                $label = (string) $entries->first()['label'];
                $parts = array_map('trim', explode(',', $label));

                return [
                    'label' => $label,
                    'name' => $parts[0],
                    'region' => count($parts) > 1 ? implode(', ', array_slice($parts, 1)) : null,
                    'trip_count' => $entries->count(),
                    'cover_image_url' => $entries->pluck('cover')->filter()->first(),
                ];
            })
            ->sortByDesc(fn (array $destination): int => $destination['trip_count'] * 10 + ($destination['cover_image_url'] !== null ? 1 : 0))
            ->take(5)
            ->values()
            ->all();

        return ['total' => $grouped->count(), 'top' => $top];
    }

    /**
     * Open-trip categories among upcoming published trips, most common first.
     *
     * @param  Collection<int, Trip>  $trips
     * @return list<array{value: string, count: int}>
     */
    private function categoryCounts(Collection $trips): array
    {
        return $trips
            ->reject(fn (Trip $trip): bool => $trip->isPastTrip())
            ->map(fn (Trip $trip): ?string => $trip->openTripDetails()['category'] ?? null)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->map(fn (int $count, string $category): array => ['value' => $category, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * The signed-in user's own and joined trips, split by phase.
     *
     * @return array{counts: array<string, int>, upcoming: list<array<string, mixed>>, past: list<array<string, mixed>>}
     */
    private function myTrips(User $user): array
    {
        $query = fn () => Trip::query()->forUserOrCollaborator($user->id)->active();

        $ongoing = $query()->inPhase(TripPhase::Ongoing)->orderedForPhase(TripPhase::Ongoing)->limit(4)->get();
        $upcoming = $query()->inPhase(TripPhase::Upcoming)->orderedForPhase(TripPhase::Upcoming)->limit(4)->get();
        $past = $query()->inPhase(TripPhase::Past)->orderedForPhase(TripPhase::Past)->limit(4)->get();

        return [
            'counts' => collect(TripPhase::cases())
                ->mapWithKeys(fn (TripPhase $phase): array => [
                    $phase->value => $query()->inPhase($phase)->count(),
                ])
                ->all(),
            'upcoming' => $ongoing->concat($upcoming)
                ->take(4)
                ->map(fn (Trip $trip): array => $this->personalTrip($trip, $user))
                ->values()
                ->all(),
            'past' => $past
                ->map(fn (Trip $trip): array => $this->personalTrip($trip, $user))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id: string, title: string, type: string, type_label: string, destination: string|null, start_date: string|null, end_date: string|null, cover_image_thumb_url: string|null, is_owner: bool, is_public: bool}
     */
    private function personalTrip(Trip $trip, User $user): array
    {
        return [
            'id' => (string) $trip->id,
            'title' => $trip->title,
            'type' => $trip->type->value,
            'type_label' => $trip->type->label(),
            'destination' => Trip::normalizeLocation($trip->getAttribute('destination'))['label'] ?? null,
            'start_date' => $trip->start_date?->toDateString(),
            'end_date' => $trip->end_date?->toDateString(),
            'cover_image_thumb_url' => $trip->coverImageThumbUrl(),
            'is_owner' => $trip->isOwnedBy($user),
            'is_public' => $trip->isPublic(),
        ];
    }
}
