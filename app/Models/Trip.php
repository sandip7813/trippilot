<?php

namespace App\Models;

use App\Enums\ExpenseSheetVisibility;
use App\Enums\OpenTripCostModel;
use App\Enums\TravelStyle;
use App\Enums\TripCollaboratorRole;
use App\Enums\TripCoverSource;
use App\Enums\TripPhase;
use App\Enums\TripRouteMode;
use App\Enums\TripScope;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Enums\TripVisibility;
use App\Services\Trips\TripCoverImageService;
use App\Services\Trips\TripRouteResolver;
use Carbon\CarbonInterface;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $id
 * @property int $user_id
 * @property TripType $type
 * @property TravelStyle|null $travel_style
 * @property string $title
 * @property array<string, mixed>|null $origin
 * @property array<string, mixed>|null $destination
 * @property TripRouteMode|null $route_mode
 * @property list<array<string, mixed>>|null $waypoints
 * @property bool|null $returns_to_origin
 * @property TripScope|null $trip_scope
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property float|null $budget
 * @property int $travelers
 * @property TripStatus $status
 * @property bool $is_favorite
 * @property string|null $notes
 * @property string|null $cover_image_path
 * @property string|null $cover_image_thumb_path
 * @property int $cover_image_version
 * @property string|null $cover_image_source
 * @property int|null $cover_image_source_index
 * @property string|null $cover_image_ref
 * @property list<string>|null $cover_image_tried_refs
 * @property bool $cover_image_exhausted
 * @property array<string, string|null>|null $cover_image_attribution
 * @property array<string, mixed>|null $road_profile
 * @property list<array<string, mixed>>|null $stops
 * @property array<string, mixed>|null $route
 * @property list<array<string, mixed>>|null $suggested_breaks
 * @property array<string, mixed>|null $amenities_cache
 * @property list<array<string, mixed>>|null $chat_messages
 * @property list<int>|null $reminders_sent
 * @property list<array{user_id: int|null, email: string, role: string, status: string, added_at: string|null}>|null $collaborators
 * @property TripVisibility|null $visibility
 * @property Carbon|null $published_at
 * @property ExpenseSheetVisibility|null $expense_sheet_visibility
 * @property array{
 *     category: string|null,
 *     max_group_size: int|null,
 *     join_deadline: string|null,
 *     difficulty: string|null,
 *     requirements: string|null,
 *     meeting_point: string|null,
 *     rules: string|null,
 *     cost_model: string|null,
 *     cost_amount: float|null,
 *     cost_currency: string|null,
 *     cost_inclusions: string|null,
 *     share_itinerary_with_members: bool|null,
 *     member_names_visible: bool|null,
 * }|null $open_trip
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected string $collection = 'trips';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'travel_style',
        'title',
        'origin',
        'destination',
        'route_mode',
        'waypoints',
        'returns_to_origin',
        'trip_scope',
        'start_date',
        'end_date',
        'budget',
        'travelers',
        'status',
        'is_favorite',
        'notes',
        'cover_image_path',
        'cover_image_thumb_path',
        'cover_image_version',
        'cover_image_source',
        'cover_image_source_index',
        'cover_image_ref',
        'cover_image_tried_refs',
        'cover_image_exhausted',
        'cover_image_attribution',
        'itinerary',
        'road_profile',
        'stops',
        'route',
        'suggested_breaks',
        'amenities_cache',
        'chat_messages',
        'collaborators',
        'reminders_sent',
        'visibility',
        'published_at',
        'expense_sheet_visibility',
        'open_trip',
    ];

    /**
     * @var list<string|array<string, int>>
     */
    protected $indexes = [
        ['user_id' => 1],
        ['status' => 1],
        ['is_favorite' => 1],
        ['travel_style' => 1],
        ['trip_scope' => 1],
        ['created_at' => -1],
        ['collaborators.user_id' => 1],
        ['visibility' => 1],
        ['published_at' => -1],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TripType::class,
            'travel_style' => TravelStyle::class,
            'route_mode' => TripRouteMode::class,
            'returns_to_origin' => 'boolean',
            'trip_scope' => TripScope::class,
            'status' => TripStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'budget' => 'float',
            'travelers' => 'integer',
            'is_favorite' => 'boolean',
            'cover_image_exhausted' => 'boolean',
            'cover_image_source_index' => 'integer',
            'cover_image_version' => 'integer',
            'visibility' => TripVisibility::class,
            'published_at' => 'datetime',
            'expense_sheet_visibility' => ExpenseSheetVisibility::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Trip $trip): void {
            app(TripCoverImageService::class)->deleteForTrip($trip);
            TripExpenseEntry::withTrashed()->where('trip_id', (string) $trip->id)->forceDelete();
            TripExpenseActivity::query()->where('trip_id', (string) $trip->id)->delete();
            TripExpenseSheet::query()->where('trip_id', (string) $trip->id)->delete();
        });
    }

    /**
     * @return array<string, mixed>|array<int, mixed>|null
     */
    public static function coerceStructuredArray(mixed $value): ?array
    {
        $value = self::decodeStructuredValue($value);

        return is_array($value) ? $value : null;
    }

    public static function decodeStructuredValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || ! str_starts_with($trimmed, '{') && ! str_starts_with($trimmed, '[')) {
            return $value;
        }

        $decoded = json_decode($trimmed, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    /**
     * @param  array<string, mixed>|null  $route
     * @return array<string, mixed>|null
     */
    public static function normalizeRoute(?array $route): ?array
    {
        if ($route === null) {
            return null;
        }

        if (array_key_exists('distance_km', $route) && $route['distance_km'] !== null) {
            $route['distance_km'] = (float) $route['distance_km'];
        }

        return $route;
    }

    /**
     * @return array{
     *     label: string|null,
     *     lat: float|null,
     *     lng: float|null,
     *     place_id: string|null,
     *     country_code: string|null,
     * }|null
     */
    public static function normalizeLocation(mixed $value): ?array
    {
        $value = self::decodeStructuredValue($value);

        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return [
                'label' => $value,
                'lat' => null,
                'lng' => null,
                'place_id' => null,
                'country_code' => null,
            ];
        }

        if (! is_array($value)) {
            return null;
        }

        $label = $value['label'] ?? null;

        if ($label === null || $label === '') {
            return null;
        }

        $countryCode = $value['country_code'] ?? null;

        return [
            'label' => $label,
            'lat' => isset($value['lat']) && $value['lat'] !== '' ? (float) $value['lat'] : null,
            'lng' => isset($value['lng']) && $value['lng'] !== '' ? (float) $value['lng'] : null,
            'place_id' => $value['place_id'] ?? null,
            'country_code' => is_string($countryCode) && $countryCode !== ''
                ? strtolower($countryCode)
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $origin
     * @param  array<string, mixed>|null  $destination
     */
    public static function resolveTripScope(?array $origin, ?array $destination): ?TripScope
    {
        if ($destination === null || self::locationCountryCode($destination) === null) {
            return null;
        }

        return self::resolveTripScopeFromLocations([$origin, $destination]);
    }

    /**
     * @param  list<array<string, mixed>|null>  $locations
     */
    public static function resolveTripScopeFromLocations(array $locations): ?TripScope
    {
        $countries = collect($locations)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $location): ?string => self::locationCountryCode($location))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($countries === []) {
            return null;
        }

        if (count($countries) > 1) {
            return TripScope::International;
        }

        return $countries[0] === 'in'
            ? TripScope::Domestic
            : TripScope::International;
    }

    /**
     * The locations that best represent this trip's cover photo, ordered by relevance.
     *
     * Prefers waypoints (the places actually explored on a multi-stop trip) over the
     * single `destination` field, which for a round trip is often just the return leg
     * (e.g. a "Golden Triangle" trip's `destination` is New Delhi even though Agra and
     * Jaipur are the highlights). Falls back to `destination` when there are no
     * distinct waypoints, and never uses `origin` or the trip title.
     *
     * @return list<array<string, mixed>>
     */
    public function coverDestinationCandidates(): array
    {
        $origin = self::normalizeLocation($this->getAttribute('origin'));
        $destination = self::normalizeLocation($this->getAttribute('destination'));
        $waypoints = self::normalizeWaypoints($this->getAttribute('waypoints'));

        $candidates = collect($waypoints)
            ->pluck('location')
            ->filter(fn (mixed $location): bool => is_array($location) && filled($location['label'] ?? null))
            ->push($destination)
            ->filter(fn (mixed $location): bool => is_array($location) && filled($location['label'] ?? null))
            ->unique(fn (array $location): string => strtolower((string) $location['label']))
            ->values();

        if ($origin !== null && $candidates->count() > 1) {
            $withoutOrigin = $candidates->reject(
                fn (array $location): bool => strtolower((string) $location['label']) === strtolower((string) $origin['label'])
            )->values();

            if ($withoutOrigin->isNotEmpty()) {
                $candidates = $withoutOrigin;
            }
        }

        return $candidates->all();
    }

    /**
     * @param  list<array<string, mixed>>|null  $waypoints
     * @return list<array<string, mixed>>
     */
    public static function normalizeWaypoints(?array $waypoints): array
    {
        if ($waypoints === null) {
            return [];
        }

        return collect($waypoints)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->map(function (array $waypoint, int $index): array {
                return [
                    'sequence' => is_numeric($waypoint['sequence'] ?? null)
                        ? (int) $waypoint['sequence']
                        : $index + 1,
                    'location' => self::normalizeLocation($waypoint['location'] ?? null),
                    'nights' => is_numeric($waypoint['nights'] ?? null)
                        ? max(0, (int) $waypoint['nights'])
                        : null,
                    'notes' => isset($waypoint['notes']) ? (string) $waypoint['notes'] : null,
                ];
            })
            ->sortBy('sequence')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $location
     */
    private static function locationCountryCode(?array $location): ?string
    {
        if ($location === null) {
            return null;
        }

        $countryCode = $location['country_code'] ?? null;

        if (! is_string($countryCode) || $countryCode === '') {
            return null;
        }

        return strtolower($countryCode);
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeRoad(Builder $query): Builder
    {
        return $query->where('type', TripType::Road->value);
    }

    public function isRoadTrip(): bool
    {
        return $this->type === TripType::Road;
    }

    public function expenseSheet(): ?TripExpenseSheet
    {
        return TripExpenseSheet::query()->where('trip_id', (string) $this->id)->first();
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === $user->id;
    }

    /**
     * @return list<array{user_id: int|null, email: string, role: string, status: string, added_at: string|null}>
     */
    public function collaboratorEntries(): array
    {
        $collaborators = self::coerceStructuredArray($this->getAttribute('collaborators')) ?? [];

        return array_values(array_filter(
            $collaborators,
            fn (mixed $entry): bool => is_array($entry) && filled($entry['email'] ?? null),
        ));
    }

    /**
     * @return list<array{user_id: int|null, email: string, role: string, status: string, added_at: string|null}>
     */
    public function pendingCollaboratorEntriesForEmail(string $email): array
    {
        return array_values(array_filter(
            $this->collaboratorEntries(),
            fn (array $entry): bool => ($entry['status'] ?? '') === 'pending'
                && strcasecmp((string) $entry['email'], $email) === 0,
        ));
    }

    public function collaboratorRole(User $user): ?TripCollaboratorRole
    {
        foreach ($this->collaboratorEntries() as $entry) {
            if ((int) ($entry['user_id'] ?? 0) === $user->id) {
                return TripCollaboratorRole::tryFrom((string) ($entry['role'] ?? ''));
            }
        }

        return null;
    }

    public function isCollaborator(User $user): bool
    {
        return $this->collaboratorRole($user) !== null;
    }

    /**
     * Owner or a collaborator (any role) can view the trip.
     */
    public function isViewableBy(User $user): bool
    {
        return $this->isOwnedBy($user) || $this->isCollaborator($user);
    }

    /**
     * Owner or an editor collaborator can change the trip.
     */
    public function isEditableBy(User $user): bool
    {
        return $this->isOwnedBy($user) || $this->collaboratorRole($user) === TripCollaboratorRole::Editor;
    }

    public function isMember(User $user): bool
    {
        return $this->collaboratorRole($user) === TripCollaboratorRole::Member;
    }

    public function isPublic(): bool
    {
        return $this->visibility === TripVisibility::Public;
    }

    /**
     * A public trip whose end date has already passed. Still viewable, but
     * joining is closed while contact stays open.
     */
    public function isPastTrip(): bool
    {
        return $this->end_date !== null && $this->end_date->isPast();
    }

    public function expenseSheetVisibility(): ExpenseSheetVisibility
    {
        return $this->expense_sheet_visibility ?? ExpenseSheetVisibility::Private;
    }

    public function isExpenseSheetSharedWith(User $user): bool
    {
        if ($this->isOwnedBy($user)) {
            return true;
        }

        return $this->expenseSheetVisibility() === ExpenseSheetVisibility::Shared
            && $this->isCollaborator($user);
    }

    /**
     * @return array{
     *     category: string|null,
     *     max_group_size: int|null,
     *     join_deadline: string|null,
     *     difficulty: string|null,
     *     requirements: string|null,
     *     meeting_point: string|null,
     *     rules: string|null,
     *     cost_model: string|null,
     *     cost_amount: float|null,
     *     cost_currency: string|null,
     *     cost_inclusions: string|null,
     *     share_itinerary_with_members: bool|null,
     *     member_names_visible: bool|null,
     * }
     */
    public function openTripDetails(): array
    {
        return self::coerceStructuredArray($this->getAttribute('open_trip')) ?? [];
    }

    public function openTripCostModel(): ?OpenTripCostModel
    {
        return OpenTripCostModel::tryFrom((string) ($this->openTripDetails()['cost_model'] ?? ''));
    }

    /**
     * Route/map data safe to show to any viewer of an open trip — a public
     * listing's whole point is to advertise where it goes, so unlike the
     * rest of the public overview this includes coordinates, not just
     * labels. Mirrors the owner's "At a glance" route panel: the same
     * city-chain labels and per-stop timeline (with nights/arrival/
     * departure dates) computed by TripRouteResolver, plus map pins.
     *
     * @return array{
     *     chain: list<string>,
     *     timeline: list<array<string, mixed>>,
     *     map_points: list<array{sequence: int, label: string, lat: float, lng: float, kind: string}>,
     * }
     */
    public function publicRouteOverview(): array
    {
        $summary = $this->routeSummaryForFrontend() ?? [];

        $origin = self::normalizeLocation($this->getAttribute('origin'));
        $destination = self::normalizeLocation($this->getAttribute('destination'));
        $returnsToOrigin = $this->returnsToOriginForFrontend();

        $mapPoints = [];
        $sequence = 0;

        if ($origin !== null && $origin['lat'] !== null) {
            $mapPoints[] = [
                'sequence' => $sequence++,
                'label' => $origin['label'],
                'lat' => $origin['lat'],
                'lng' => $origin['lng'],
                'kind' => 'origin',
            ];
        }

        foreach ($this->waypointsForFrontend() as $waypoint) {
            $location = $waypoint['location'] ?? null;

            if (is_array($location) && ($location['lat'] ?? null) !== null) {
                $mapPoints[] = [
                    'sequence' => $sequence++,
                    'label' => $location['label'],
                    'lat' => $location['lat'],
                    'lng' => $location['lng'],
                    'kind' => 'stay',
                ];
            }
        }

        if ($destination !== null && $destination['lat'] !== null) {
            $mapPoints[] = [
                'sequence' => $sequence++,
                'label' => $destination['label'],
                'lat' => $destination['lat'],
                'lng' => $destination['lng'],
                'kind' => 'stay',
            ];
        }

        if ($returnsToOrigin && $origin !== null && $origin['lat'] !== null && count($mapPoints) > 1) {
            $mapPoints[] = [
                'sequence' => $sequence++,
                'label' => $origin['label'],
                'lat' => $origin['lat'],
                'lng' => $origin['lng'],
                'kind' => 'return',
            ];
        }

        return [
            'chain' => $summary['route_display_points'] ?? [],
            'timeline' => $summary['route_stops'] ?? [],
            'map_points' => $mapPoints,
        ];
    }

    /**
     * Itinerary preview safe to show to any viewer of an open trip, only
     * when the owner opted in via the same flag used to share it with
     * accepted members. The budget breakdown stays private regardless.
     *
     * @return array{days: list<array<string, mixed>>, summary: string, packing_list: list<string>}|null
     */
    public function publicItineraryOverview(): ?array
    {
        if (! $this->sharesItineraryWithMembers()) {
            return null;
        }

        $itinerary = $this->itineraryForFrontend();

        if ($itinerary['days'] === [] && $itinerary['summary'] === '') {
            return null;
        }

        return [
            'days' => $itinerary['days'],
            'summary' => $itinerary['summary'],
            'packing_list' => $itinerary['packing_list'],
        ];
    }

    /**
     * Fields the owner must fill in before a trip can be published. Kept
     * deliberately small: enough for a listing to make sense to a visitor,
     * without demanding every optional detail. Difficulty is intentionally
     * not required.
     *
     * @return list<string> human-readable labels of the missing fields
     */
    public function missingRequiredOpenTripFields(): array
    {
        $details = $this->openTripDetails();

        $required = [
            'category' => 'category',
            'max_group_size' => 'max group size',
            'cost_model' => 'cost model',
        ];

        return collect($required)
            ->reject(fn (string $label, string $field): bool => filled($details[$field] ?? null))
            ->values()
            ->all();
    }

    public function sharesItineraryWithMembers(): bool
    {
        return (bool) ($this->openTripDetails()['share_itinerary_with_members'] ?? true);
    }

    public function memberNamesVisible(): bool
    {
        return (bool) ($this->openTripDetails()['member_names_visible'] ?? false);
    }

    public function maxGroupSize(): ?int
    {
        $size = $this->openTripDetails()['max_group_size'] ?? null;

        return $size !== null ? (int) $size : null;
    }

    /**
     * Count of accepted members, excluding the owner.
     */
    public function acceptedMemberCount(): int
    {
        return collect($this->collaboratorEntries())
            ->where('status', 'accepted')
            ->where('role', TripCollaboratorRole::Member->value)
            ->count();
    }

    public function hasOpenSeats(): bool
    {
        $max = $this->maxGroupSize();

        return $max === null || $this->acceptedMemberCount() < $max;
    }

    /**
     * Whether joining is currently open: public, not past, not full, and
     * before the join deadline (when one is set).
     */
    public function isJoinable(): bool
    {
        if (! $this->isPublic() || $this->isPastTrip() || ! $this->hasOpenSeats()) {
            return false;
        }

        $deadline = $this->openTripDetails()['join_deadline'] ?? null;

        if ($deadline === null) {
            return true;
        }

        return ! Carbon::parse($deadline)->isPast();
    }

    /**
     * @return list<array{user_id: int|null, name: string|null, email: string, role: string, role_label: string, status: string, added_at: string|null}>
     */
    public function collaboratorsForFrontend(): array
    {
        $entries = $this->collaboratorEntries();

        if ($entries === []) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', collect($entries)->pluck('user_id')->filter())
            ->get()
            ->keyBy('id');

        return collect($entries)
            ->map(function (array $entry) use ($users): array {
                $user = isset($entry['user_id']) ? $users->get((int) $entry['user_id']) : null;
                $role = TripCollaboratorRole::tryFrom((string) ($entry['role'] ?? '')) ?? TripCollaboratorRole::Viewer;

                return [
                    'user_id' => $user?->id,
                    'name' => $user?->name,
                    'email' => $user?->email ?? (string) $entry['email'],
                    'role' => $role->value,
                    'role_label' => $role->label(),
                    'status' => $user !== null ? 'accepted' : (string) ($entry['status'] ?? 'pending'),
                    'added_at' => $entry['added_at'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The owner sees full collaborator details (including email). Members
     * see only first names of other accepted members, and only once the
     * owner turns that on; everyone else sees nothing.
     *
     * @return list<array<string, mixed>>
     */
    private function collaboratorsVisibleTo(bool $isOwner, bool $isMember): array
    {
        if ($isOwner) {
            return $this->collaboratorsForFrontend();
        }

        if (! $isMember || ! $this->memberNamesVisible()) {
            return [];
        }

        $entries = collect($this->collaboratorEntries())
            ->where('status', 'accepted')
            ->pluck('user_id')
            ->filter();

        return User::query()
            ->whereIn('id', $entries)
            ->get()
            ->map(fn (User $user): array => ['name' => $user->first_name])
            ->values()
            ->all();
    }

    /**
     * @return array{name: string|null, email: string|null}|null
     */
    public function ownerForFrontend(): ?array
    {
        $owner = User::query()->find((int) $this->user_id);

        if ($owner === null) {
            return null;
        }

        return [
            'name' => $owner->name,
            'email' => $owner->email,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function routeData(): ?array
    {
        return self::normalizeRoute(
            self::coerceStructuredArray($this->getAttribute('route')),
        );
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeForUserOrCollaborator(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $query) use ($userId): void {
            $query->where('user_id', $userId)
                ->orWhere('collaborators.user_id', $userId);
        });
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeSharedWithUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', '!=', $userId)
            ->where('collaborators.user_id', $userId);
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('visibility', TripVisibility::Public->value);
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeUpcomingOrOngoing(Builder $query): Builder
    {
        $today = Carbon::today();

        return $query->where(fn (Builder $query) => $query
            ->whereNull('end_date')
            ->orWhere('end_date', '>=', $today));
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopePastTrips(Builder $query): Builder
    {
        return $query->where('end_date', '<', Carbon::today());
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeFavorites(Builder $query): Builder
    {
        return $query->where('is_favorite', true);
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', TripStatus::Archived);
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', TripStatus::Archived->value);
    }

    /**
     * Trips without a start date count as upcoming; a missing end date means
     * a single-day trip.
     *
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeInPhase(Builder $query, TripPhase $phase): Builder
    {
        $today = Carbon::today();

        return match ($phase) {
            TripPhase::Upcoming => $query->where(fn (Builder $query) => $query
                ->whereNull('start_date')
                ->orWhere('start_date', '>', $today)),
            TripPhase::Ongoing => $query
                ->where('start_date', '<=', $today)
                ->where(fn (Builder $query) => $query
                    ->where('end_date', '>=', $today)
                    ->orWhere(fn (Builder $query) => $query
                        ->whereNull('end_date')
                        ->where('start_date', '>=', $today))),
            TripPhase::Past => $query->where(fn (Builder $query) => $query
                ->where('end_date', '<', $today)
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('end_date')
                    ->where('start_date', '<', $today))),
        };
    }

    /**
     * Upcoming and ongoing trips are soonest first; past trips latest first.
     *
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeOrderedForPhase(Builder $query, TripPhase $phase): Builder
    {
        return match ($phase) {
            TripPhase::Past => $query->orderByDesc('end_date')->orderByDesc('start_date'),
            default => $query->orderBy('start_date')->orderByDesc('created_at'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toFrontend(?User $viewer = null): array
    {
        $origin = self::normalizeLocation($this->getAttribute('origin'));
        $destination = self::normalizeLocation($this->getAttribute('destination'));
        $viewer ??= auth()->user();
        $isOwner = $viewer !== null && $this->isOwnedBy($viewer);
        $isMember = $viewer !== null && $this->isMember($viewer);

        return [
            'id' => (string) $this->id,
            'user_id' => $this->user_id,
            'is_owner' => $isOwner,
            'is_member' => $isMember,
            'visibility' => $isOwner ? ($this->visibility?->value ?? 'private') : null,
            'published_at' => $isOwner ? $this->published_at?->toIso8601String() : null,
            'expense_sheet_visibility' => $isOwner ? $this->expenseSheetVisibility()->value : null,
            'open_trip' => $isOwner ? $this->openTripDetails() : null,
            'collaborator_role' => $viewer ? $this->collaboratorRole($viewer)?->value : null,
            'collaborators' => $this->collaboratorsVisibleTo($isOwner, $isMember),
            'owner' => (! $isOwner && $viewer !== null) ? $this->ownerForFrontend() : null,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'travel_style' => $this->travel_style?->value,
            'travel_style_label' => $this->travel_style?->label(),
            'title' => $this->title,
            'origin' => $origin,
            'destination' => $destination,
            'route_mode' => $this->routeModeForFrontend(),
            'waypoints' => $this->waypointsForFrontend(),
            'returns_to_origin' => $this->returnsToOriginForFrontend(),
            'route_summary' => $this->routeSummaryForFrontend(),
            'trip_scope' => $this->trip_scope?->value,
            'trip_scope_label' => $this->trip_scope?->label(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'budget' => $isMember ? null : $this->budget,
            'travelers' => $this->travelers,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_favorite' => $this->is_favorite,
            'notes' => $isMember ? null : $this->notes,
            'cover_image_url' => $this->coverImageUrl(),
            'cover_image_thumb_url' => $this->coverImageThumbUrl(),
            'cover_image_version' => (int) ($this->cover_image_version ?? 0),
            'cover_image_source' => $this->cover_image_source,
            'cover_image_source_label' => $this->coverSourceLabel(),
            'cover_image_exhausted' => (bool) ($this->cover_image_exhausted ?? false),
            'cover_image_attribution' => $this->cover_image_attribution,
            'itinerary' => ($isMember && ! $this->sharesItineraryWithMembers()) ? Trip::emptyItinerary() : $this->itineraryForFrontend(),
            'chat_messages' => $isMember ? [] : $this->chatMessagesForFrontend(),
            'road_profile' => $this->isRoadTrip() ? $this->roadProfileForFrontend() : null,
            'stops' => $this->isRoadTrip() ? $this->stopsForFrontend() : [],
            'route' => $this->isRoadTrip() ? $this->routeForFrontend() : null,
            'suggested_breaks' => $this->isRoadTrip() ? $this->suggestedBreaksForFrontend() : [],
            'amenities_cache' => $this->isRoadTrip() ? $this->amenitiesCacheForFrontend() : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected static function newFactory(): TripFactory
    {
        return TripFactory::new();
    }

    /**
     * @return array{
     *     days: array<int, mixed>,
     *     summary: string,
     *     packing_list: array<int, string>,
     *     budget_breakdown: array<string, mixed>
     * }
     */
    public static function emptyItinerary(): array
    {
        return [
            'days' => [],
            'summary' => '',
            'packing_list' => [],
            'budget_breakdown' => [],
        ];
    }

    /**
     * @return list<array{id: string, role: string, content: string, created_at: string, patch_applied?: bool, rag_sources?: list<array{document_id: string, title: string, score?: float|null}>}>
     */
    public static function normalizeChatMessages(mixed $messages): array
    {
        if (! is_array($messages)) {
            return [];
        }

        $normalized = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $role = (string) ($message['role'] ?? '');
            $content = trim((string) ($message['content'] ?? ''));

            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }

            $entry = [
                'id' => (string) ($message['id'] ?? ''),
                'role' => $role,
                'content' => $content,
                'created_at' => (string) ($message['created_at'] ?? now()->toIso8601String()),
            ];

            if ($role === 'assistant' && isset($message['patch_applied'])) {
                $entry['patch_applied'] = (bool) $message['patch_applied'];
            }

            if ($role === 'assistant' && is_array($message['rag_sources'] ?? null)) {
                $entry['rag_sources'] = array_values(array_filter(array_map(
                    function (mixed $source): ?array {
                        if (! is_array($source)) {
                            return null;
                        }

                        $documentId = (string) ($source['document_id'] ?? '');
                        $title = (string) ($source['title'] ?? '');

                        if ($documentId === '' || $title === '') {
                            return null;
                        }

                        $normalized = [
                            'document_id' => $documentId,
                            'title' => $title,
                        ];

                        if (isset($source['score']) && is_numeric($source['score'])) {
                            $normalized['score'] = (float) $source['score'];
                        }

                        return $normalized;
                    },
                    $message['rag_sources'],
                )));
            }

            if ($entry['id'] === '') {
                continue;
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    public function hasGeneratedItinerary(): bool
    {
        $itinerary = self::coerceStructuredArray($this->getAttribute('itinerary')) ?? [];
        $days = $itinerary['days'] ?? [];

        return is_array($days) && $days !== [];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function materialAttributesDiffer(array $validated): bool
    {
        $materialKeys = [
            'type',
            'travel_style',
            'origin',
            'destination',
            'route_mode',
            'waypoints',
            'returns_to_origin',
            'start_date',
            'end_date',
            'travelers',
        ];

        foreach ($materialKeys as $key) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }

            if ($this->materialValueDiffers($key, $validated[$key])) {
                return true;
            }
        }

        return false;
    }

    private function materialValueDiffers(string $key, mixed $incoming): bool
    {
        return match ($key) {
            'type' => $this->type->value !== (string) $incoming,
            'travel_style' => ($this->travel_style instanceof TravelStyle ? $this->travel_style->value : null) !== ($incoming !== null && $incoming !== '' ? (string) $incoming : null),
            'travelers' => (int) $this->travelers !== (int) $incoming,
            'start_date' => $this->dateValue($this->start_date) !== $this->normalizeDateInput($incoming),
            'end_date' => $this->dateValue($this->end_date) !== $this->normalizeDateInput($incoming),
            'origin' => $this->normalizedLocation($this->getAttribute('origin')) !== self::normalizeLocation($incoming),
            'destination' => $this->normalizedLocation($this->getAttribute('destination')) !== self::normalizeLocation($incoming),
            'route_mode' => ($this->route_mode instanceof TripRouteMode ? $this->route_mode->value : TripRouteMode::Simple->value) !== ($incoming instanceof TripRouteMode ? $incoming->value : (string) $incoming),
            'waypoints' => self::normalizeWaypoints($this->getAttribute('waypoints')) !== self::normalizeWaypoints(is_array($incoming) ? $incoming : null),
            'returns_to_origin' => (bool) ($this->returns_to_origin ?? true) !== (bool) $incoming,
            default => false,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizedLocation(mixed $value): ?array
    {
        return self::normalizeLocation($value);
    }

    private function dateValue(?CarbonInterface $date): ?string
    {
        return $date?->toDateString();
    }

    private function normalizeDateInput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->toDateString();
    }

    /**
     * @return array{
     *     days: array<int, mixed>,
     *     summary: string,
     *     packing_list: array<int, string>,
     *     budget_breakdown: array<string, mixed>
     * }
     */
    private function itineraryForFrontend(): array
    {
        $itinerary = self::coerceStructuredArray($this->getAttribute('itinerary')) ?? [];

        return [
            'days' => is_array($itinerary['days'] ?? null) ? $itinerary['days'] : [],
            'summary' => (string) ($itinerary['summary'] ?? ''),
            'packing_list' => is_array($itinerary['packing_list'] ?? null)
                ? array_values(array_map(strval(...), $itinerary['packing_list']))
                : [],
            'budget_breakdown' => is_array($itinerary['budget_breakdown'] ?? null)
                ? $itinerary['budget_breakdown']
                : [],
        ];
    }

    /**
     * @return list<array{id: string, role: string, content: string, created_at: string, patch_applied?: bool, rag_sources?: list<array{document_id: string, title: string, score?: float|null}>}>
     */
    private function chatMessagesForFrontend(): array
    {
        return self::normalizeChatMessages($this->getAttribute('chat_messages'));
    }

    public function coverImageUrl(): ?string
    {
        return $this->publicStorageUrl($this->cover_image_path);
    }

    public function coverImageThumbUrl(): ?string
    {
        $thumbUrl = $this->publicStorageUrl($this->cover_image_thumb_path);

        return $thumbUrl ?? $this->coverImageUrl();
    }

    public function coverSourceLabel(): ?string
    {
        if (! is_string($this->cover_image_source) || $this->cover_image_source === '') {
            return null;
        }

        return TripCoverSource::tryFrom($this->cover_image_source)?->label();
    }

    private function publicStorageUrl(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $version = (int) ($this->cover_image_version ?? 0);

        if ($version > 0) {
            return asset('storage/'.$path).'?v='.$version;
        }

        $timestamp = $this->updated_at?->getTimestamp() ?? time();

        return asset('storage/'.$path).'?v='.$timestamp;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function roadProfileForFrontend(): ?array
    {
        $profile = self::coerceStructuredArray($this->getAttributes()['road_profile'] ?? $this->road_profile);

        if (! is_array($profile) || $profile === []) {
            return null;
        }

        return $profile;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stopsForFrontend(): array
    {
        $stops = self::coerceStructuredArray($this->getAttributes()['stops'] ?? $this->stops);

        if (! is_array($stops)) {
            return [];
        }

        return array_values(array_filter($stops, is_array(...)));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function routeForFrontend(): ?array
    {
        $route = self::normalizeRoute(
            self::coerceStructuredArray($this->getAttribute('route')),
        );

        return is_array($route) && $route !== [] ? $route : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function suggestedBreaksForFrontend(): array
    {
        $breaks = self::coerceStructuredArray($this->getAttributes()['suggested_breaks'] ?? $this->suggested_breaks);

        if (! is_array($breaks)) {
            return [];
        }

        return array_values(array_filter($breaks, is_array(...)));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function amenitiesCacheForFrontend(): ?array
    {
        $cache = self::coerceStructuredArray($this->getAttributes()['amenities_cache'] ?? $this->amenities_cache);

        return is_array($cache) && $cache !== [] ? $cache : null;
    }

    private function routeModeForFrontend(): string
    {
        if ($this->route_mode instanceof TripRouteMode) {
            return $this->route_mode->value;
        }

        $waypoints = self::normalizeWaypoints($this->getAttribute('waypoints'));

        return count($waypoints) > 1
            ? TripRouteMode::MultiCity->value
            : TripRouteMode::Simple->value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function waypointsForFrontend(): array
    {
        return self::normalizeWaypoints($this->getAttribute('waypoints'));
    }

    private function returnsToOriginForFrontend(): bool
    {
        return $this->returns_to_origin ?? true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function routeSummaryForFrontend(): ?array
    {
        return app(TripRouteResolver::class)->summary($this);
    }
}
