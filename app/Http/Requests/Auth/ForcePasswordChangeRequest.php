<?php

namespace App\Http\Requests\Auth;

use App\Concerns\PasswordValidationRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class ForcePasswordChangeRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string|Closure>
     */
    public function rules(): array
    {
        return [
            'password' => [
                ...$this->passwordRules(),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && Hash::check($value, $this->user()->password)) {
                        $fail(__('Your new password must be different from the one-time password.'));
                    }
                },
            ],
        ];
    }
}
