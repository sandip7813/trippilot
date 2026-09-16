<?php

namespace App\Http\Requests;

use App\Enums\TripCollaboratorRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTripCollaboratorRequest extends FormRequest
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
            'email' => [
                'required',
                'email:rfc',
                // The bare "email" rule only checks RFC syntax, which allows
                // single-label hosts (e.g. "user@yopmail") with no TLD.
                'regex:/^[^@\s]+@[^@\s]+\.[^@\s]+$/',
            ],
            'role' => ['required', new Enum(TripCollaboratorRole::class)],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $email = $this->string('email')->toString();

                if ($email !== '' && strcasecmp($email, $this->user()?->email ?? '') === 0) {
                    $validator->errors()->add('email', __('You already own this trip.'));
                }
            },
        ];
    }
}
