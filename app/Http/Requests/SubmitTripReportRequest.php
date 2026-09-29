<?php

namespace App\Http\Requests;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitTripReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Trip $trip */
        $trip = $this->route('trip');

        return $this->user() !== null && $this->user()->can('report', $trip);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(['spam', 'scam', 'inappropriate', 'other'])],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
