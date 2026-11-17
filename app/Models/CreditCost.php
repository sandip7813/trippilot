<?php

namespace App\Models;

use App\Enums\CreditService;
use Database\Factories\CreditCostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The number of credits a billable AI service costs, set by a super admin.
 *
 * @property int $id
 * @property CreditService $service
 * @property int $credits
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CreditCost extends Model
{
    /** @use HasFactory<CreditCostFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'service',
        'credits',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service' => CreditService::class,
            'credits' => 'integer',
        ];
    }
}
