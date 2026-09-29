<?php

namespace App\Http\Requests;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;

class SendTripInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Trip $trip */
        $trip = $this->route('trip');

        return $this->user() !== null && $this->user()->can('contact', $trip);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
