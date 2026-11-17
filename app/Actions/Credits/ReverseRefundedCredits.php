<?php

namespace App\Actions\Credits;

use App\Enums\CreditOrderStatus;
use App\Models\CreditOrder;
use App\Models\User;
use App\Notifications\CreditPurchaseRefundedNotification;
use App\Services\Credits\CreditLedger;
use Illuminate\Support\Facades\DB;

class ReverseRefundedCredits
{
    public function __construct(private CreditLedger $ledger) {}

    /**
     * Take back the share of an order's credits that matches the refunded
     * amount and tell the user. $amountRefundedPaise is Razorpay's running
     * total for the payment, so repeated webhooks for the same refund change
     * nothing and send no further notifications.
     */
    public function __invoke(CreditOrder $order, int $amountRefundedPaise): CreditOrder
    {
        $notification = null;

        $updatedOrder = DB::transaction(function () use ($order, $amountRefundedPaise, &$notification): CreditOrder {
            $lockedOrder = CreditOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status === CreditOrderStatus::Created
                || $amountRefundedPaise <= $lockedOrder->amount_refunded_paise) {
                return $lockedOrder;
            }

            $refundedPaise = min($amountRefundedPaise, $lockedOrder->amount_paise);
            $creditsToReverse = intdiv($lockedOrder->credits * $refundedPaise, $lockedOrder->amount_paise)
                - $lockedOrder->credits_reversed;

            $reversal = $creditsToReverse > 0
                ? $this->ledger->reversePurchase(User::query()->findOrFail($lockedOrder->user_id), $lockedOrder, $creditsToReverse)
                : null;

            $notification = CreditPurchaseRefundedNotification::forOrder(
                $lockedOrder,
                $refundedPaise - $lockedOrder->amount_refunded_paise,
                $reversal === null ? 0 : abs($reversal->amount),
            );

            $lockedOrder->update([
                'amount_refunded_paise' => $refundedPaise,
                'credits_reversed' => $lockedOrder->credits_reversed + max($creditsToReverse, 0),
                'status' => $refundedPaise >= $lockedOrder->amount_paise
                    ? CreditOrderStatus::Refunded
                    : $lockedOrder->status,
            ]);

            return $lockedOrder;
        });

        if ($notification !== null) {
            $updatedOrder->user?->notify($notification);
        }

        return $updatedOrder;
    }
}
