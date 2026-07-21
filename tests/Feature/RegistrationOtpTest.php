<?php

use App\Mail\RegistrationOtpMail;
use App\Models\User;
use App\Services\Auth\RegistrationOtpService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration otp can be requested for a new email', function () {
    Mail::fake();

    $response = $this->from(route('register'))
        ->post(route('register.otp'), [
            'email' => 'new-user@example.com',
        ]);

    $response->assertRedirect(route('register'));
    $response->assertSessionHas('otp_sent', true);
    $response->assertSessionHas('otp_email', 'new-user@example.com');

    Mail::assertSent(RegistrationOtpMail::class, function (RegistrationOtpMail $mail) {
        return strlen($mail->code) === 6;
    });
});

test('registration otp can be verified and cleared', function () {
    Mail::fake();

    $service = app(RegistrationOtpService::class);
    $email = 'otp-user@example.com';

    $service->send($email);

    $cachedValue = Cache::get('registration_otp:'.sha1($email));
    expect($cachedValue)->toBeString();

    Mail::assertSent(RegistrationOtpMail::class, function (RegistrationOtpMail $mail) use ($service, $email) {
        expect($service->matches($email, $mail->code))->toBeTrue();
        expect($service->matches($email, '000000'))->toBeFalse();

        return true;
    });

    $service->forget($email);

    expect(Cache::get('registration_otp:'.sha1($email)))->toBeNull();
});

test('registration otp cannot be requested for an existing email', function () {
    Mail::fake();

    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->from(route('register'))
        ->post(route('register.otp'), [
            'email' => 'existing@example.com',
        ]);

    $response->assertSessionHasErrors('email');
    Mail::assertNothingSent();
});

test('registration otp endpoint requires a valid email', function () {
    Mail::fake();

    $response = $this->from(route('register'))
        ->post(route('register.otp'), [
            'email' => 'not-an-email',
        ]);

    $response->assertSessionHasErrors('email');
    Mail::assertNothingSent();
});
