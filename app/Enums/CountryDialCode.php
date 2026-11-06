<?php

namespace App\Enums;

enum CountryDialCode: string
{
    case India = '+91';
    case UnitedStates = '+1';
    case UnitedKingdom = '+44';
    case UnitedArabEmirates = '+971';
    case SaudiArabia = '+966';
    case Qatar = '+974';
    case Singapore = '+65';
    case Malaysia = '+60';
    case Australia = '+61';
    case Germany = '+49';
    case France = '+33';
    case Nepal = '+977';
    case Bangladesh = '+880';
    case SriLanka = '+94';
    case Bhutan = '+975';

    public function label(): string
    {
        return match ($this) {
            self::India => 'India',
            self::UnitedStates => 'United States / Canada',
            self::UnitedKingdom => 'United Kingdom',
            self::UnitedArabEmirates => 'United Arab Emirates',
            self::SaudiArabia => 'Saudi Arabia',
            self::Qatar => 'Qatar',
            self::Singapore => 'Singapore',
            self::Malaysia => 'Malaysia',
            self::Australia => 'Australia',
            self::Germany => 'Germany',
            self::France => 'France',
            self::Nepal => 'Nepal',
            self::Bangladesh => 'Bangladesh',
            self::SriLanka => 'Sri Lanka',
            self::Bhutan => 'Bhutan',
        };
    }

    /**
     * The number of digits in a mobile number for this country, excluding the
     * dial code and any leading trunk prefix such as "0".
     *
     * @return array{min: int, max: int}
     */
    public function mobileNumberLength(): array
    {
        return match ($this) {
            self::India, self::UnitedStates, self::UnitedKingdom, self::Nepal, self::Bangladesh => ['min' => 10, 'max' => 10],
            self::UnitedArabEmirates, self::SaudiArabia, self::Australia, self::France, self::SriLanka => ['min' => 9, 'max' => 9],
            self::Qatar, self::Singapore, self::Bhutan => ['min' => 8, 'max' => 8],
            self::Malaysia => ['min' => 9, 'max' => 10],
            self::Germany => ['min' => 10, 'max' => 11],
        };
    }

    /**
     * @return list<array{value: string, label: string, minDigits: int, maxDigits: int}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $dialCode): array => [
                'value' => $dialCode->value,
                'label' => $dialCode->label(),
                'minDigits' => $dialCode->mobileNumberLength()['min'],
                'maxDigits' => $dialCode->mobileNumberLength()['max'],
            ],
            self::cases(),
        );
    }
}
