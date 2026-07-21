<?php

namespace App\Rules;

use App\Services\Auth\RegistrationOtpService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidRegistrationOtp implements ValidationRule
{
    public function __construct(private readonly string $email) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{6}$/', $value)) {
            $fail('Please enter the 6-digit verification code.');

            return;
        }

        $otpService = app(RegistrationOtpService::class);

        if (! $otpService->matches($this->email, $value)) {
            $fail('The verification code is invalid or has expired.');
        }
    }
}
