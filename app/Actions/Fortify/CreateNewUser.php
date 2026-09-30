<?php

namespace App\Actions\Fortify;

use App\Actions\Trips\ResolvePendingTripCollaborators;
use App\Concerns\ProfileValidationRules;
use App\Mail\RegistrationPasswordMail;
use App\Models\User;
use App\Rules\Recaptcha;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use ProfileValidationRules;

    /**
     * Validate and create a newly registered user with a generated one-time
     * password, which is emailed to them and must be changed on first login.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'first_name' => $this->firstNameRules(),
            'last_name' => $this->lastNameRules(),
            'email' => $this->emailRules(),
            'mobile_number' => ['required', 'string', 'max:20'],
            'g-recaptcha-response' => [
                Recaptcha::isActive() ? 'required' : 'nullable',
                new Recaptcha,
            ],
        ])->validate();

        $oneTimePassword = Str::password(12, symbols: false);

        $user = User::create([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
            'mobile_number' => $input['mobile_number'],
            'password' => $oneTimePassword,
        ]);

        $user->forceFill(['must_change_password' => true])->save();

        Mail::to($user->email)->send(new RegistrationPasswordMail(
            firstName: $user->first_name,
            email: $user->email,
            password: $oneTimePassword,
        ));

        app(ResolvePendingTripCollaborators::class)($user);

        return $user;
    }
}
