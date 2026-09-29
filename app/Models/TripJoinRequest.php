<?php

namespace App\Models;

use App\Enums\TripJoinRequestStatus;
use Database\Factories\TripJoinRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $id
 * @property string $trip_id
 * @property int $user_id
 * @property int $travelers_count
 * @property string|null $phone
 * @property string|null $message
 * @property TripJoinRequestStatus $status
 * @property Carbon|null $decided_at
 * @property int|null $decided_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TripJoinRequest extends Model
{
    /** @use HasFactory<TripJoinRequestFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected string $collection = 'trip_join_requests';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'user_id',
        'travelers_count',
        'phone',
        'message',
        'status',
        'decided_at',
        'decided_by',
    ];

    /**
     * @var list<string|array<string, int>>
     */
    protected $indexes = [
        ['trip_id' => 1],
        ['user_id' => 1],
        ['status' => 1],
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'travelers_count' => 'integer',
            'status' => TripJoinRequestStatus::class,
            'decided_at' => 'datetime',
            'decided_by' => 'integer',
        ];
    }

    protected static function newFactory(): TripJoinRequestFactory
    {
        return TripJoinRequestFactory::new();
    }

    public function isPending(): bool
    {
        return $this->status === TripJoinRequestStatus::Pending;
    }
}
