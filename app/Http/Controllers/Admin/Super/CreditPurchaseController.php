<?php

namespace App\Http\Controllers\Admin\Super;

use App\Enums\CreditOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\CreditOrder;
use Inertia\Inertia;
use Inertia\Response;

class CreditPurchaseController extends Controller
{
    /**
     * List paid and refunded credit pack orders, newest first.
     */
    public function index(): Response
    {
        $orders = CreditOrder::query()
            ->with('user')
            ->where('status', '!=', CreditOrderStatus::Created)
            ->latest('paid_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/super/CreditPurchases', [
            'orders' => $orders->through(fn (CreditOrder $order): array => [
                'id' => $order->id,
                'user_name' => $order->user?->name,
                'user_email' => $order->user?->email,
                'pack_name' => $order->pack_name,
                'amount' => $order->amount_paise / 100,
                'amount_refunded' => $order->amount_refunded_paise / 100,
                'credits' => $order->credits,
                'credits_reversed' => $order->credits_reversed,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'razorpay_payment_id' => $order->razorpay_payment_id,
                'paid_at' => $order->paid_at?->toIso8601String(),
            ]),
        ]);
    }
}
