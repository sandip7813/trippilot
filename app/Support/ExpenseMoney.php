<?php

namespace App\Support;

use Illuminate\Support\Number;

class ExpenseMoney
{
    public static function toMinor(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function toMajor(int $minor): float
    {
        return round($minor / 100, 2);
    }

    public static function format(int $minor): string
    {
        $formatted = Number::currency(
            self::toMajor($minor),
            (string) config('trippilot.currency', 'INR'),
            (string) config('trippilot.currency_locale', 'en-IN'),
        );

        return $formatted === false ? number_format(self::toMajor($minor), 2) : $formatted;
    }

    public static function formatMajor(float|int $amount): string
    {
        return self::format(self::toMinor($amount));
    }
}
