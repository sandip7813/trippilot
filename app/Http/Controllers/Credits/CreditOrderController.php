<?php

namespace App\Http\Controllers\Credits;

use App\Actions\Credits\CreateCreditOrder;
use App\Actions\Credits\VerifyCreditPayment;
use App\Exceptions\CreditPurchaseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Credits\StoreCreditOrderRequest;
use App\Http\Requests\Credits\VerifyCreditPaymentRequest;
use App\Models\CreditOrder;
use App\Models\CreditPack;
use App\Services\Payments\RazorpayClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CreditOrderController extends Controller
{
    /**
     * Start buying a credit pack. Returns the options Razorpay Checkout
     * needs to open the payment window.
     */
    public function store(
        StoreCreditOrderRequest $request,
        CreateCreditOrder $createCreditOrder,
        RazorpayClient $razorpay,
    ): JsonResponse {
        $user = $request->user();
        $pack = CreditPack::query()->findOrFail($request->integer('credit_pack_id'));

        try {
            $order = $createCreditOrder($user, $pack);
        } catch (CreditPurchaseException $exception) {
            throw ValidationException::withMessages(['credit_pack_id' => $exception->getMessage()]);
        }

        return response()->json([
            'order_id' => $order->id,
            'checkout' => [
                'key' => $razorpay->keyId(),
                'order_id' => $order->razorpay_order_id,
                'amount' => $order->amount_paise,
                'currency' => $order->currency,
                'name' => config('app.name'),
                'description' => __(':credits credits (:pack pack)', [
                    'credits' => $order->credits,
                    'pack' => $order->pack_name,
                ]),
                'prefill' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);
    }

    /**
     * Confirm the payment Razorpay Checkout reported and add the credits.
     */
    public function verify(
        VerifyCreditPaymentRequest $request,
        CreditOrder $order,
        VerifyCreditPayment $verifyCreditPayment,
    ): RedirectResponse {
        try {
            $order = $verifyCreditPayment(
                $order,
                $request->validated('razorpay_payment_id'),
                $request->validated('razorpay_signature'),
            );
        } catch (CreditPurchaseException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return to_route('credits.buy');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payment received. :credits credits were added to your balance.', ['credits' => $order->credits]),
        ]);

        return to_route('credits.history');
    }
}
