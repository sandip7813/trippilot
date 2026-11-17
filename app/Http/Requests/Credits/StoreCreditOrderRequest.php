<?php

namespace App\Http\Requests\Credits;

use App\Services\Credits\CreditPurchases;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditOrderRequest extends FormRequest
{
    public function authorize(CreditPurchases $creditPurchases): bool
    {
        return $this->user() !== null && $creditPurchases->isAvailableFor($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'credit_pack_id' => [
                'required',
                'integer',
                Rule::exists('credit_packs', 'id')->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'credit_pack_id.exists' => __('This credit pack is no longer available.'),
        ];
    }
}
