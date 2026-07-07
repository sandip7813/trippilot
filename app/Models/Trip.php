<?php

namespace App\Models;

use App\Enums\TravelStyle;
use App\Enums\TripStatus;
use App\Enums\TripType;
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
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property float|null $budget
 * @property int $travelers
 * @property TripStatus $status
 * @property bool $is_favorite
 * @property string|null $notes
 * @property array<string, mixed> $itinerary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'trips';

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
        'start_date',
        'end_date',
        'budget',
        'travelers',
        'status',
        'is_favorite',
        'notes',
        'itinerary',
    ];

    /**
     * @var list<string|array<string, int>>
     */
    protected $indexes = [
        ['user_id' => 1],
        ['status' => 1],
        ['is_favorite' => 1],
        ['travel_style' => 1],
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
            'status' => TripStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'budget' => 'float',
            'travelers' => 'integer',
            'is_favorite' => 'boolean',
            'origin' => 'array',
            'destination' => 'array',
            'itinerary' => 'array',
        ];
    }

    /**
     * @return array{label: string|null, lat: float|null, lng: float|null, place_id: string|null}|null
     */
    public static function normalizeLocation(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return [
                'label' => $value,
                'lat' => null,
                'lng' => null,
                'place_id' => null,
            ];
        }

        if (! is_array($value)) {
            return null;
        }

        $label = $value['label'] ?? null;

        if ($label === null || $label === '') {
            return null;
        }

        return [
            'label' => $label,
            'lat' => isset($value['lat']) && $value['lat'] !== '' ? (float) $value['lat'] : null,
            'lng' => isset($value['lng']) && $value['lng'] !== '' ? (float) $value['lng'] : null,
            'place_id' => $value['place_id'] ?? null,
        ];
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
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'budget' => $this->budget,
            'travelers' => $this->travelers,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_favorite' => $this->is_favorite,
            'notes' => $this->notes,
            'itinerary' => $this->itineraryForFrontend(),
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
        $days = $this->itinerary['days'] ?? [];

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
            'travel_style' => ($this->travel_style?->value ?? null) !== ($incoming !== null && $incoming !== '' ? (string) $incoming : null),
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

    private function dateValue(?Carbon $date): ?string
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
        $itinerary = $this->itinerary ?? [];

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
}
