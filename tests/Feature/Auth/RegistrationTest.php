<?php

use App\Mail\RegistrationPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users register without a password and receive a one-time password by email', function () {
    Mail::fake();

    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'mobile_number' => '9876543210',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status');
    $this->assertGuest();

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user)
        ->name->toBe('Test User')
        ->mobile_number->toBe('9876543210')
        ->must_change_password->toBeTrue()
        ->email_verified_at->toBeNull();

    Mail::assertSent(RegistrationPasswordMail::class, function (RegistrationPasswordMail $mail) use ($user) {
        return $mail->hasTo('test@example.com')
            && strlen($mail->password) === 12
            && Hash::check($mail->password, $user->password);
    });
});

test('registration requires first name, last name, mobile number and email', function () {
    Mail::fake();

    $response = $this->post(route('register.store'), []);

    $response->assertSessionHasErrors(['first_name', 'last_name', 'mobile_number', 'email']);
    $this->assertGuest();
    Mail::assertNothingSent();
});

test('registration fails for an email that is already taken', function () {
    Mail::fake();
    User::factory()->create(['email' => 'test@example.com']);

    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'mobile_number' => '9876543210',
    ]);

    $response->assertSessionHasErrors('email');
    Mail::assertNothingSent();
});

function enableRecaptcha(): void
{
    config([
        'recaptcha.enabled' => true,
        'recaptcha.site_key' => 'test-site-key',
        'recaptcha.secret_key' => 'test-secret',
        'recaptcha.action' => 'register',
        'recaptcha.score_threshold' => 0.5,
    ]);
}

test('registration screen passes the recaptcha site key when recaptcha is configured', function () {
    enableRecaptcha();

    $this->get(route('register'))
        ->assertInertia(fn ($page) => $page
            ->component('auth/Register')
            ->where('recaptcha.enabled', true)
            ->where('recaptcha.siteKey', 'test-site-key'));
});

test('registration screen disables recaptcha when keys are missing', function () {
    config(['recaptcha.enabled' => true, 'recaptcha.site_key' => null, 'recaptcha.secret_key' => null]);

    $this->get(route('register'))
        ->assertInertia(fn ($page) => $page->where('recaptcha.enabled', false));
});

test('registration succeeds with a valid recaptcha token', function () {
    enableRecaptcha();
    Mail::fake();
    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.9,
            'action' => 'register',
        ]),
    ]);

    $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'mobile_number' => '9876543210',
        'g-recaptcha-response' => 'valid-token',
    ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeTrue();
    Http::assertSent(fn ($request) => $request['response'] === 'valid-token'
        && $request['secret'] === 'test-secret');
});

test('registration is rejected without a recaptcha token', function () {
    enableRecaptcha();
    Mail::fake();
    Http::fake();

    $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'mobile_number' => '9876543210',
    ])->assertSessionHasErrors('g-recaptcha-response');

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
    Mail::assertNothingSent();
});

test('registration is rejected when recaptcha scores the request as a bot', function () {
    enableRecaptcha();
    Mail::fake();
    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.1,
            'action' => 'register',
        ]),
    ]);

    $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'mobile_number' => '9876543210',
        'g-recaptcha-response' => 'bot-token',
    ])->assertSessionHasErrors('g-recaptcha-response');

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
    Mail::assertNothingSent();
});
