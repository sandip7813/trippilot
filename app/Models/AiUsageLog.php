<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $id
 * @property int|null $user_id
 * @property string $feature
 * @property string $model
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $total_tokens
 * @property float $cost_usd
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AiUsageLog extends Model
{
    protected $connection = 'mongodb';

    protected string $collection = 'ai_usage_logs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'feature',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost_usd',
    ];

    /**
     * @var list<string|array<string, int>>
     */
    protected $indexes = [
        ['created_at' => -1],
        ['user_id' => 1],
        ['feature' => 1],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'cost_usd' => 'float',
        ];
    }
}
