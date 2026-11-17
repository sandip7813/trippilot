<?php

namespace App\Models;

use App\Enums\CreditOrderStatus;
use Database\Factories\CreditOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Razorpay order for one credit pack. The price and credits are copied
 * from the pack when the order is created, so later pack edits never change
 * what the user paid for.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $credit_pack_id
 * @property string $pack_name
 * @property int $amount_paise
 * @property string $currency
 * @property int $credits
 * @property CreditOrderStatus $status
 * @property string $razorpay_order_id
 * @property string|null $razorpay_payment_id
 * @property int $amount_refunded_paise
 * @property int $credits_reversed
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CreditOrder extends Model
{
    /** @use HasFactory<CreditOrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'credit_pack_id',
        'pack_name',
        'amount_paise',
        'currency',
        'credits',
        'status',
        'razorpay_order_id',
        'razorpay_payment_id',
        'amount_refunded_paise',
        'credits_reversed',
        'paid_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CreditPack, $this>
     */
    public function creditPack(): BelongsTo
    {
        return $this->belongsTo(CreditPack::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CreditOrderStatus::class,
            'amount_paise' => 'integer',
            'credits' => 'integer',
            'amount_refunded_paise' => 'integer',
            'credits_reversed' => 'integer',
            'paid_at' => 'datetime',
        ];
    }
}
