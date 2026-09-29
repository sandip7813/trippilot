<?php

namespace App\Http\Requests;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;

class SubmitTripJoinRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Trip $trip */
        $trip = $this->route('trip');

        return $this->user() !== null && $this->user()->can('requestToJoin', $trip);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'travelers_count' => ['required', 'integer', 'min:1', 'max:20'],
            'phone' => ['required', 'string', 'max:20'],
            'message' => ['nullable', 'string', 'max:2000'],
            'accepts_terms' => ['accepted'],
        ];
    }
}
