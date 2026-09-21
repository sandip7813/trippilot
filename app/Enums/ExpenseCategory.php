<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Stay = 'stay';
    case Transport = 'transport';
    case Food = 'food';
    case Activities = 'activities';
    case Shopping = 'shopping';
    case Fuel = 'fuel';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Stay => 'Stay',
            self::Transport => 'Transport',
            self::Food => 'Food & drinks',
            self::Activities => 'Activities',
            self::Shopping => 'Shopping',
            self::Fuel => 'Fuel & tolls',
            self::Other => 'Miscellaneous',
        };
    }
}
