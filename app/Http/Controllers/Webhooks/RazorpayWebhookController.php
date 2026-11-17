<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Credits\CompleteCreditOrder;
use App\Actions\Credits\ReverseRefundedCredits;
use App\Http\Controllers\Controller;
use App\Models\CreditOrder;
use App\Services\Payments\RazorpayClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives Razorpay webhooks. Captured payments add credits even when the
 * user closed the tab before checkout reported back, and processed refunds
 * take back the matching unspent credits.
 */
class RazorpayWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        RazorpayClient $razorpay,
        CompleteCreditOrder $completeCreditOrder,
        ReverseRefundedCredits $reverseRefundedCredits,
    ): Response {
        if (! $razorpay->isValidWebhookSignature($request->getContent(), (string) $request->header('X-Razorpay-Signature'))) {
            return response('Invalid signature', 400);
        }

        $payment = (array) $request->input('payload.payment.entity', []);

        match ($request->input('event')) {
            'payment.captured', 'order.paid' => $this->completeOrder($payment, $completeCreditOrder),
            'refund.processed' => $this->reverseRefund($payment, (array) $request->input('payload.refund.entity', []), $reverseRefundedCredits),
            default => null,
        };

        return response('OK');
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function completeOrder(array $payment, CompleteCreditOrder $completeCreditOrder): void
    {
        $order = CreditOrder::query()->where('razorpay_order_id', $payment['order_id'] ?? null)->first();

        if ($order === null
            || ($payment['status'] ?? null) !== 'captured'
            || (int) ($payment['amount'] ?? 0) !== $order->amount_paise) {
            return;
        }

        $completeCreditOrder($order, (string) $payment['id']);
    }

    /**
     * @param  array<string, mixed>  $payment
     * @param  array<string, mixed>  $refund
     */
    private function reverseRefund(array $payment, array $refund, ReverseRefundedCredits $reverseRefundedCredits): void
    {
        $order = CreditOrder::query()
            ->where('razorpay_payment_id', $refund['payment_id'] ?? $payment['id'] ?? null)
            ->first();

        if ($order === null) {
            return;
        }

        $amountRefunded = isset($payment['amount_refunded'])
            ? (int) $payment['amount_refunded']
            : $order->amount_refunded_paise + (int) ($refund['amount'] ?? 0);

        $reverseRefundedCredits($order, $amountRefunded);
    }
}
