<?php

namespace App\Models;

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
 * @property string $title
 * @property string|null $destination
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
        'title',
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
        ['created_at' => -1],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TripType::class,
            'status' => TripStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'budget' => 'float',
            'travelers' => 'integer',
            'is_favorite' => 'boolean',
            'itinerary' => 'array',
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
        return [
            'id' => (string) $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'title' => $this->title,
            'destination' => $this->destination,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'budget' => $this->budget,
            'travelers' => $this->travelers,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_favorite' => $this->is_favorite,
            'notes' => $this->notes,
            'itinerary' => $this->itinerary ?? ['days' => [], 'summary' => ''],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected static function newFactory(): TripFactory
    {
        return TripFactory::new();
    }
}
