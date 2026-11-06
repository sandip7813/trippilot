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
        'mobile_country_code' => '+91',
        'mobile_number' => '9876543210',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status');
    $this->assertGuest();

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user)
        ->name->toBe('Test User')
        ->mobile_country_code->toBe('+91')
        ->mobile_number->toBe('9876543210')
        ->must_change_password->toBeTrue()
        ->email_verified_at->toBeNull();

    Mail::assertSent(RegistrationPasswordMail::class, function (RegistrationPasswordMail $mail) use ($user) {
        return $mail->hasTo('test@example.com')
            && strlen($mail->password) === 12
            && Hash::check($mail->password, $user->password);
    });
});

test('registration requires first name, last name and email', function () {
    Mail::fake();

    $response = $this->post(route('register.store'), []);

    $response->assertSessionHasErrors(['first_name', 'last_name', 'email']);
    $response->assertSessionDoesntHaveErrors(['mobile_number', 'mobile_country_code']);
    $this->assertGuest();
    Mail::assertNothingSent();
});

test('users can register without a mobile number', function () {
    Mail::fake();

    $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'mobile_country_code' => '+91',
        'mobile_number' => '',
    ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

    expect(User::query()->where('email', 'test@example.com')->firstOrFail())
        ->mobile_country_code->toBeNull()
        ->mobile_number->toBeNull();
});

test('registration validates the mobile number length for the selected country', function (string $countryCode, string $mobileNumber, bool $isValid) {
    Mail::fake();

    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'mobile_country_code' => $countryCode,
        'mobile_number' => $mobileNumber,
    ]);

    if ($isValid) {
        $response->assertSessionHasNoErrors();
        expect(User::query()->where('email', 'test@example.com')->firstOrFail())
            ->mobile_country_code->toBe($countryCode)
            ->mobile_number->toBe($mobileNumber);
    } else {
        $response->assertSessionHasErrors('mobile_number');
    }
})->with([
    'UAE with 9 digits' => ['+971', '501234567', true],
    'UAE with 10 digits' => ['+971', '5012345678', false],
    'Singapore with 8 digits' => ['+65', '81234567', true],
    'Singapore with 10 digits' => ['+65', '8123456789', false],
    'Germany with 11 digits' => ['+49', '15123456789', true],
    'Germany with 9 digits' => ['+49', '151234567', false],
]);

test('registration rejects indian mobile numbers that are not exactly ten digits', function (string $mobileNumber) {
    Mail::fake();

    $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'mobile_country_code' => '+91',
        'mobile_number' => $mobileNumber,
    ])->assertSessionHasErrors('mobile_number');

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
    Mail::assertNothingSent();
})->with(['98765', '98765432101', '98765abcde', '+919876543210', '98765 43210']);

test('registration rejects an unknown mobile country code', function (?string $countryCode) {
    Mail::fake();

    $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'mobile_country_code' => $countryCode,
        'mobile_number' => '9876543210',
    ])->assertSessionHasErrors('mobile_country_code');

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
})->with(['+999', null]);

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
        'mobile_country_code' => '+91',
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

test('registration rejects emails without a proper domain', function (string $email) {
    Mail::fake();

    $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => $email,
        'mobile_number' => '9876543210',
    ])->assertSessionHasErrors('email');

    expect(User::query()->where('email', $email)->exists())->toBeFalse();
    Mail::assertNothingSent();
})->with(['dfsdfds@asd', 'user@localhost', 'user@domain.', 'no-at-sign.com']);
