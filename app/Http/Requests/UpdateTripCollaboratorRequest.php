<?php

namespace App\Http\Requests;

use App\Enums\TripCollaboratorRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateTripCollaboratorRequest extends FormRequest
{
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
            'role' => ['required', new Enum(TripCollaboratorRole::class)],
        ];
    }
}
