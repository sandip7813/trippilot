<?php

namespace App\Actions\Credits;

use App\Enums\CreditOrderStatus;
use App\Exceptions\CreditPurchaseException;
use App\Models\CreditOrder;
use App\Models\CreditPack;
use App\Models\User;
use App\Services\Payments\RazorpayClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class CreateCreditOrder
{
    public function __construct(private RazorpayClient $razorpay) {}

    /**
     * Create a Razorpay order for the pack and remember its price and credits.
     *
     * @throws CreditPurchaseException
     */
    public function __invoke(User $user, CreditPack $pack): CreditOrder
    {
        try {
            $razorpayOrder = $this->razorpay->createOrder(
                $pack->price_paise,
                'credits_'.$user->id.'_'.now()->format('YmdHisv'),
                ['user_id' => (string) $user->id, 'credit_pack_id' => (string) $pack->id],
            );
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            throw new CreditPurchaseException(__('We could not start the payment. Please try again in a moment.'));
        }

        return $user->creditOrders()->create([
            'credit_pack_id' => $pack->id,
            'pack_name' => $pack->name,
            'amount_paise' => $pack->price_paise,
            'currency' => 'INR',
            'credits' => $pack->credits,
            'status' => CreditOrderStatus::Created,
            'razorpay_order_id' => $razorpayOrder['id'],
        ]);
    }
}
