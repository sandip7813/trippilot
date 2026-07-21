<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\PotentiallyTranslatedString;

class Recaptcha implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! config('recaptcha.enabled')) {
            return;
        }

        $secretKey = config('recaptcha.secret_key');

        if (blank($secretKey)) {
            $fail('Captcha verification is not configured.');

            return;
        }

        if (! is_string($value) || blank($value)) {
            $fail('Captcha verification failed. Please try again.');

            return;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secretKey,
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

        $expectedAction = config('recaptcha.action');

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
