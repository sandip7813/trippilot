<?php

namespace App\Models;

use App\Enums\TripReportStatus;
use Database\Factories\TripReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $id
 * @property string $trip_id
 * @property int $reporter_id
 * @property string $reason
 * @property string|null $message
 * @property TripReportStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TripReport extends Model
{
    /** @use HasFactory<TripReportFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected string $collection = 'trip_reports';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'reporter_id',
        'reason',
        'message',
        'status',
    ];

    /**
     * @var list<string|array<string, int>>
     */
    protected $indexes = [
        ['trip_id' => 1],
        ['status' => 1],
    ];

    protected function casts(): array
    {
        return [
            'reporter_id' => 'integer',
            'status' => TripReportStatus::class,
        ];
    }

    protected static function newFactory(): TripReportFactory
    {
        return TripReportFactory::new();
    }
}
