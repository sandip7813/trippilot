<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Razorpay REST API and its signature checks.
 */
class RazorpayClient
{
    public function isConfigured(): bool
    {
        return filled($this->keyId()) && filled($this->keySecret());
    }

    public function keyId(): ?string
    {
        return config('integrations.payments.razorpay.key_id');
    }

    /**
     * @param  array<string, string>  $notes
     * @return array{id: string, amount: int, currency: string}
     *
     * @throws RequestException
     */
    public function createOrder(int $amountPaise, string $receipt, array $notes = []): array
    {
        return $this->request()
            ->post('orders', [
                'amount' => $amountPaise,
                'currency' => 'INR',
                'receipt' => $receipt,
                'notes' => $notes,
            ])
            ->throw()
            ->json();
    }

    /**
     * @return array{id: string, order_id: string|null, amount: int, currency: string, status: string}
     *
     * @throws RequestException
     */
    public function fetchPayment(string $paymentId): array
    {
        return $this->request()
            ->get('payments/'.rawurlencode($paymentId))
            ->throw()
            ->json();
    }

    /**
     * Capture an authorized payment. Only needed when automatic capture is
     * switched off in the Razorpay dashboard.
     *
     * @return array{id: string, status: string}
     *
     * @throws RequestException
     */
    public function capturePayment(string $paymentId, int $amountPaise, string $currency): array
    {
        return $this->request()
            ->post('payments/'.rawurlencode($paymentId).'/capture', [
                'amount' => $amountPaise,
                'currency' => $currency,
            ])
            ->throw()
            ->json();
    }

    /**
     * Check the signature Razorpay Checkout returns after a successful payment.
     */
    public function isValidPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, (string) $this->keySecret());

        return hash_equals($expected, $signature);
    }

    /**
     * Check the X-Razorpay-Signature header of a webhook request.
     */
    public function isValidWebhookSignature(string $payload, string $signature): bool
    {
        $secret = config('integrations.payments.razorpay.webhook_secret');

        if (blank($secret) || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, (string) $secret), $signature);
    }

    private function keySecret(): ?string
    {
        return config('integrations.payments.razorpay.key_secret');
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('integrations.payments.razorpay.base_url'))
            ->withBasicAuth((string) $this->keyId(), (string) $this->keySecret())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15);
    }
}
