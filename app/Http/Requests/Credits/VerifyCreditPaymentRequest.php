<?php

namespace App\Http\Requests\Credits;

use App\Models\CreditOrder;
use Illuminate\Foundation\Http\FormRequest;

class VerifyCreditPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CreditOrder $order */
        $order = $this->route('order');

        return $this->user()?->id === $order->user_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'razorpay_payment_id' => ['required', 'string', 'max:64'],
            'razorpay_signature' => ['required', 'string', 'max:255'],
        ];
    }
}
