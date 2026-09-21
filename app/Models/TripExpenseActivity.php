<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $id
 * @property string $trip_id
 * @property array{user_id: int, name: string} $actor
 * @property string $action
 * @property string $summary
 * @property bool $by_other
 * @property string|null $entry_id
 * @property Carbon|null $created_at
 */
class TripExpenseActivity extends Model
{
    protected $connection = 'mongodb';

    protected string $collection = 'trip_expense_activities';

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'actor',
        'action',
        'summary',
        'by_other',
        'entry_id',
    ];

    /**
     * @var list<string|array<string, int>>
     */
    protected $indexes = [
        ['trip_id' => 1, 'created_at' => -1],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'by_other' => 'boolean',
        ];
    }
}
