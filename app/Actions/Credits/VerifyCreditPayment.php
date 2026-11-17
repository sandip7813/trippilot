<?php

namespace App\Actions\Credits;

use App\Exceptions\CreditPurchaseException;
use App\Models\CreditOrder;
use App\Services\Payments\RazorpayClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class VerifyCreditPayment
{
    public function __construct(
        private RazorpayClient $razorpay,
        private CompleteCreditOrder $completeCreditOrder,
    ) {}

    /**
     * Confirm the payment Razorpay Checkout reported, capture it if needed,
     * and add the credits.
     *
     * @throws CreditPurchaseException
     */
    public function __invoke(CreditOrder $order, string $paymentId, string $signature): CreditOrder
    {
        if (! $this->razorpay->isValidPaymentSignature($order->razorpay_order_id, $paymentId, $signature)) {
            throw new CreditPurchaseException(__('We could not verify this payment. If money was deducted, it will be added as credits shortly or refunded by Razorpay.'));
        }

        try {
            $payment = $this->razorpay->fetchPayment($paymentId);

            if (($payment['order_id'] ?? null) !== $order->razorpay_order_id
                || (int) ($payment['amount'] ?? 0) !== $order->amount_paise) {
                throw new CreditPurchaseException(__('This payment does not match your order.'));
            }

            if ($payment['status'] === 'authorized') {
                $payment = $this->razorpay->capturePayment($paymentId, $order->amount_paise, $order->currency);
            }
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            throw new CreditPurchaseException(__('We could not confirm your payment with Razorpay yet. Your credits will be added automatically once it is confirmed.'));
        }

        if (($payment['status'] ?? null) !== 'captured') {
            throw new CreditPurchaseException(__('Your payment has not completed yet. Your credits will be added automatically once it is confirmed.'));
        }

        return ($this->completeCreditOrder)($order, $paymentId);
    }
}
