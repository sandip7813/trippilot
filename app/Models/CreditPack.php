<?php

namespace App\Models;

use Database\Factories\CreditPackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A credit pack users can buy. Prices are stored in paise (₹1 = 100 paise).
 *
 * @property int $id
 * @property string $name
 * @property int $price_paise
 * @property int $credits
 * @property bool $is_featured
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CreditPack extends Model
{
    /** @use HasFactory<CreditPackFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'price_paise',
        'credits',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    /**
     * @param  Builder<CreditPack>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<CreditPack>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('price_paise');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_paise' => 'integer',
            'credits' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
