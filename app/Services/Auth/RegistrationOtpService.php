<?php

namespace App\Services\Auth;

use App\Mail\RegistrationOtpMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RegistrationOtpService
{
    private const int TTL_MINUTES = 10;

    private const int CODE_LENGTH = 6;

    public function send(string $email): void
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $code = $this->generateCode();

        Cache::put(
            $this->cacheKey($normalizedEmail),
            $this->hashCode($normalizedEmail, $code),
            now()->addMinutes(self::TTL_MINUTES),
        );

        Mail::to($normalizedEmail)->send(new RegistrationOtpMail(
            code: $code,
            expiresInMinutes: self::TTL_MINUTES,
        ));
    }

    public function matches(string $email, string $code): bool
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $cachedHash = Cache::get($this->cacheKey($normalizedEmail));

        if (! is_string($cachedHash)) {
            return false;
        }

        return hash_equals($cachedHash, $this->hashCode($normalizedEmail, $code));
    }

    public function forget(string $email): void
    {
        Cache::forget($this->cacheKey($this->normalizeEmail($email)));
    }

    public function ttlMinutes(): int
    {
        return self::TTL_MINUTES;
    }

    private function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    private function cacheKey(string $email): string
    {
        return 'registration_otp:'.sha1($email);
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 10 ** self::CODE_LENGTH - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function hashCode(string $email, string $code): string
    {
        return hash_hmac('sha256', $email.'|'.$code, (string) config('app.key'));
    }
}
