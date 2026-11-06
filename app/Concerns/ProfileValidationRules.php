<?php

namespace App\Concerns;

use App\Enums\CountryDialCode;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    use TripValidationRules;

    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null, ?string $mobileCountryCode = null): array
    {
        return [
            'first_name' => $this->firstNameRules(),
            'last_name' => $this->lastNameRules(),
            'email' => $this->emailRules($userId),
            'mobile_country_code' => $this->mobileCountryCodeRules(),
            'mobile_number' => $this->mobileNumberRules($mobileCountryCode),
            ...$this->locationRules('home_city'),
        ];
    }

    /**
     * Get the validation rules used to validate user first names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function firstNameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user last names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function lastNameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate the dial code of a user's mobile number.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    protected function mobileCountryCodeRules(): array
    {
        return ['nullable', 'required_with:mobile_number', Rule::enum(CountryDialCode::class)];
    }

    /**
     * Get the validation rules used to validate user mobile numbers, sized
     * for the selected country's number length.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function mobileNumberRules(?string $mobileCountryCode = null): array
    {
        $length = (CountryDialCode::tryFrom((string) $mobileCountryCode) ?? CountryDialCode::India)->mobileNumberLength();

        return [
            'nullable',
            $length['min'] === $length['max']
                ? "digits:{$length['min']}"
                : "digits_between:{$length['min']},{$length['max']}",
        ];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email:rfc,filter',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
