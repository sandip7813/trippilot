<?php

use App\Rules\Recaptcha;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'recaptcha.enabled' => true,
        'recaptcha.secret_key' => 'test-secret',
        'recaptcha.action' => 'register',
        'recaptcha.score_threshold' => 0.5,
    ]);
});

test('recaptcha v3 accepts valid score and action', function () {
    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.9,
            'action' => 'register',
        ]),
    ]);

    $failed = false;

    app(Recaptcha::class)->validate('g-recaptcha-response', 'valid-token', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

test('recaptcha v3 rejects low scores', function () {
    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.2,
            'action' => 'register',
        ]),
    ]);

    $failed = false;

    app(Recaptcha::class)->validate('g-recaptcha-response', 'valid-token', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

test('recaptcha v3 rejects mismatched action', function () {
    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.9,
            'action' => 'login',
        ]),
    ]);

    $failed = false;

    app(Recaptcha::class)->validate('g-recaptcha-response', 'valid-token', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

test('recaptcha validation is skipped when disabled', function () {
    config(['recaptcha.enabled' => false]);

    Http::fake();

    $failed = false;

    app(Recaptcha::class)->validate('g-recaptcha-response', '', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
    Http::assertNothingSent();
});
