<?php

namespace App\Actions\Fortify;

use App\Actions\Trips\ResolvePendingTripCollaborators;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Rules\Recaptcha;
use App\Rules\ValidRegistrationOtp;
use App\Services\Auth\RegistrationOtpService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'otp' => [
                'required',
                'string',
                'digits:6',
                new ValidRegistrationOtp($input['email'] ?? ''),
            ],
            'g-recaptcha-response' => [
                config('recaptcha.enabled') ? 'required' : 'nullable',
                new Recaptcha,
            ],
        ])->validate();

        $user = User::create([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
            'mobile_number' => $input['mobile_number'] ?? null,
            'password' => $input['password'],
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        app(RegistrationOtpService::class)->forget($input['email']);
        Session::forget(['otp_sent', 'otp_email']);
        app(ResolvePendingTripCollaborators::class)($user);

        return $user;
    }
}
