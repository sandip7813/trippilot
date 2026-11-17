<?php

namespace App\Actions\Credits;

use App\Enums\CreditOrderStatus;
use App\Models\CreditOrder;
use App\Models\User;
use App\Notifications\CreditsPurchasedNotification;
use App\Services\Credits\CreditLedger;
use Illuminate\Support\Facades\DB;

class CompleteCreditOrder
{
    public function __construct(private CreditLedger $ledger) {}

    /**
     * Mark a captured order as paid, add its credits, and tell the user.
     * Both the checkout callback and the webhook call this, so an order that
     * is already paid is returned unchanged: its credits are never added
     * twice and the user is only notified once.
     */
    public function __invoke(CreditOrder $order, string $paymentId): CreditOrder
    {
        $notification = null;

        $completedOrder = DB::transaction(function () use ($order, $paymentId, &$notification): CreditOrder {
            $lockedOrder = CreditOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status !== CreditOrderStatus::Created) {
                return $lockedOrder;
            }

            $purchase = $this->ledger->purchase(User::query()->findOrFail($lockedOrder->user_id), $lockedOrder);

            $lockedOrder->update([
                'status' => CreditOrderStatus::Paid,
                'razorpay_payment_id' => $paymentId,
                'paid_at' => now(),
            ]);

            $notification = CreditsPurchasedNotification::forOrder($lockedOrder, $purchase->balance_after);

            return $lockedOrder;
        });

        if ($notification !== null) {
            $completedOrder->user?->notify($notification);
        }

        return $completedOrder;
    }
}
