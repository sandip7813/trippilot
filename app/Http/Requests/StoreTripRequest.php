<?php

namespace App\Http\Requests;

use App\Concerns\TripValidationRules;
use App\Enums\TripStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTripRequest extends FormRequest
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
            ...$this->tripRules(),
            'status' => ['sometimes', Rule::enum(TripStatus::class)],
        ];
    }
}
