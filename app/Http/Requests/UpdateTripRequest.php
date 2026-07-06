<?php

namespace App\Http\Requests;

use App\Concerns\TripValidationRules;
use App\Enums\TripStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTripRequest extends FormRequest
{
    use TripValidationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->tripRules(updating: true),
            'status' => ['sometimes', Rule::enum(TripStatus::class)],
            'is_favorite' => ['sometimes', 'boolean'],
        ];
    }
}
