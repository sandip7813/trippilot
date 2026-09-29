<?php

namespace App\Enums;

enum TripReportStatus: string
{
    case Open = 'open';
    case Reviewed = 'reviewed';
    case ActionTaken = 'action_taken';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Reviewed => 'Reviewed',
            self::ActionTaken => 'Action taken',
        };
    }
}
