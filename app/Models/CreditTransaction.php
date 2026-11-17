<?php

namespace App\Models;

use App\Enums\CreditService;
use App\Enums\CreditTransactionType;
use Database\Factories\CreditTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry in a user's append-only credit ledger. A positive amount adds
 * credits and a negative amount spends them; balance_after is the user's
 * balance once this entry was applied.
 *
 * @property int $id
 * @property int $user_id
 * @property CreditTransactionType $type
 * @property int $amount
 * @property int $balance_after
 * @property CreditService|null $service
 * @property string|null $trip_id
 * @property int|null $related_transaction_id
 * @property int|null $credit_order_id
 * @property int|null $granted_by
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CreditTransaction extends Model
{
    /** @use HasFactory<CreditTransactionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_after',
        'service',
        'trip_id',
        'related_transaction_id',
        'credit_order_id',
        'granted_by',
        'note',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CreditTransactionType::class,
            'service' => CreditService::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }
}
