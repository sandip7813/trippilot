<?php

namespace App\Http\Requests;

use App\Enums\ContactTopic;
use App\Rules\Recaptcha;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactMessageRequest extends FormRequest
{
    /**
     * Guests fill in their own name and email; signed-in users send from
     * their account, so those fields are not taken from the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isGuest = $this->user() === null;

        return [
            'name' => [$isGuest ? 'required' : 'nullable', 'string', 'max:120'],
            'email' => [$isGuest ? 'required' : 'nullable', 'string', 'email:rfc,filter', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'topic' => ['required', Rule::enum(ContactTopic::class)],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'g-recaptcha-response' => [
                $isGuest && Recaptcha::isActive() ? 'required' : 'nullable',
                ...($isGuest ? [new Recaptcha('contact')] : []),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.min' => 'Please tell us a little more (at least 10 characters).',
        ];
    }
}
