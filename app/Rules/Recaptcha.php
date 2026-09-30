<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\PotentiallyTranslatedString;

class Recaptcha implements ValidationRule
{
    /**
     * @param  string|null  $action  The reCAPTCHA v3 action the token must carry; defaults to the signup action.
     */
    public function __construct(private ?string $action = null) {}

    /**
     * Whether signups must pass reCAPTCHA. Both keys are required: without a
     * site key the form cannot request a token, so enforcing it server-side
     * would block every signup.
     */
    public static function isActive(): bool
    {
        return (bool) config('recaptcha.enabled')
            && filled(config('recaptcha.site_key'))
            && filled(config('recaptcha.secret_key'));
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isActive()) {
            return;
        }

        if (! is_string($value) || blank($value)) {
            $fail('Captcha verification failed. Please try again.');

            return;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('recaptcha.secret_key'),
            'response' => $value,
            'remoteip' => request()->ip(),
        ]);

        if (! $response->successful()) {
            $fail('Captcha verification failed. Please try again.');

            return;
        }

        /** @var array{success?: bool, score?: float, action?: string} $payload */
        $payload = $response->json();

        if (! ($payload['success'] ?? false)) {
            $fail('Captcha verification failed. Please try again.');

            return;
        }

        $expectedAction = $this->action ?? config('recaptcha.action');

        if (($payload['action'] ?? '') !== $expectedAction) {
            $fail('Captcha verification failed. Please try again.');

            return;
        }

        $scoreThreshold = (float) config('recaptcha.score_threshold');

        if (($payload['score'] ?? 0) < $scoreThreshold) {
            $fail('Captcha verification failed. Please try again.');
        }
    }
}
