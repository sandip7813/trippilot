<?php

namespace App\Enums;

enum ContactTopic: string
{
    case General = 'general';
    case TripPlanning = 'trip_planning';
    case Account = 'account';
    case Problem = 'problem';
    case Partnership = 'partnership';
    case Feedback = 'feedback';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General question',
            self::TripPlanning => 'Help planning a trip',
            self::Account => 'Account & login',
            self::Problem => 'Report a problem',
            self::Partnership => 'Partnerships',
            self::Feedback => 'Feedback & ideas',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $topic): array => ['value' => $topic->value, 'label' => $topic->label()],
            self::cases(),
        );
    }
}
