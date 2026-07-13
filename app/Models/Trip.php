<?php

namespace App\Models;

use App\Enums\TravelStyle;
use App\Enums\TripCoverSource;
use App\Enums\TripScope;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Services\Trips\TripCoverImageService;
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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TripType::class,
            'travel_style' => TravelStyle::class,
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
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Trip $trip): void {
            app(TripCoverImageService::class)->deleteForTrip($trip);
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
        $destinationCountry = self::locationCountryCode($destination);

        if ($destinationCountry === null) {
            return null;
        }

        $originCountry = self::locationCountryCode($origin);

        if ($originCountry === null) {
            return $destinationCountry === 'in'
                ? TripScope::Domestic
                : TripScope::International;
        }

        return $originCountry === $destinationCountry
            ? TripScope::Domestic
            : TripScope::International;
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
     * @return array<string, mixed>
     */
    public function toFrontend(): array
    {
        $origin = self::normalizeLocation($this->getAttribute('origin'));
        $destination = self::normalizeLocation($this->getAttribute('destination'));

        return [
            'id' => (string) $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'travel_style' => $this->travel_style?->value,
            'travel_style_label' => $this->travel_style?->label(),
            'title' => $this->title,
            'origin' => $origin,
            'destination' => $destination,
            'trip_scope' => $this->trip_scope?->value,
            'trip_scope_label' => $this->trip_scope?->label(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'budget' => $this->budget,
            'travelers' => $this->travelers,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_favorite' => $this->is_favorite,
            'notes' => $this->notes,
            'cover_image_url' => $this->coverImageUrl(),
            'cover_image_thumb_url' => $this->coverImageThumbUrl(),
            'cover_image_version' => (int) ($this->cover_image_version ?? 0),
            'cover_image_source' => $this->cover_image_source,
            'cover_image_source_label' => $this->coverSourceLabel(),
            'cover_image_exhausted' => (bool) ($this->cover_image_exhausted ?? false),
            'cover_image_attribution' => $this->cover_image_attribution,
            'itinerary' => $this->itineraryForFrontend(),
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
}
