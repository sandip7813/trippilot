<?php

use App\Mail\RegistrationOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register after email otp verification', function () {
    Mail::fake();

    $this->from(route('register'))
        ->post(route('register.otp'), [
            'email' => 'test@example.com',
        ]);

    $otp = null;

    Mail::assertSent(RegistrationOtpMail::class, function (RegistrationOtpMail $mail) use (&$otp) {
        $otp = $mail->code;

        return true;
    });

    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'otp' => $otp,
    ]);

    $this->assertAuthenticated();
    expect(auth()->user())
        ->email->toBe('test@example.com')
        ->name->toBe('Test User')
        ->email_verified_at->not->toBeNull();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registration fails without a valid otp', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'otp' => '000000',
    ]);

    $response->assertSessionHasErrors('otp');
    $this->assertGuest();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('otp status survives a failed registration attempt on redisplay', function () {
    Mail::fake();

    $this->from(route('register'))
        ->post(route('register.otp'), [
            'email' => 'test@example.com',
        ]);

    $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'otp' => '000000',
    ])->assertSessionHasErrors('otp');

    $response = $this->get(route('register'));

    $response->assertInertia(fn ($page) => $page
        ->where('otpStatus.sent', true)
        ->where('otpStatus.email', 'test@example.com'));
});

test('otp status expires once the code is no longer pending', function () {
    Mail::fake();

    $this->from(route('register'))
        ->post(route('register.otp'), [
            'email' => 'test@example.com',
        ]);

    Cache::flush();

    $response = $this->get(route('register'));

    $response->assertInertia(fn ($page) => $page
        ->where('otpStatus.sent', false)
        ->where('otpStatus.email', null));
});

test('resending a code too soon shows a friendly error instead of a 429 page', function () {
    Mail::fake();

    $this->from(route('register'))
        ->post(route('register.otp'), ['email' => 'test@example.com']);

    $response = $this->from(route('register'))
        ->post(route('register.otp'), ['email' => 'test@example.com']);

    $response->assertSessionHasErrors('email');
    $response->assertRedirect(route('register'));
});

test('registration fails without otp', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('otp');
    $this->assertGuest();
});
