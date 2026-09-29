<?php

namespace App\Http\Requests;

use App\Concerns\OpenTripDetailsValidationRules;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOpenTripDetailsRequest extends FormRequest
{
    use OpenTripDetailsValidationRules;

    public function authorize(): bool
    {
        /** @var Trip $trip */
        $trip = $this->route('trip');

        return $this->user() !== null && $this->user()->can('publish', $trip);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->openTripDetailsRules();
    }
}
