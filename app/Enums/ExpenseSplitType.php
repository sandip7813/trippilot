<?php

namespace App\Enums;

enum ExpenseSplitType: string
{
    case Equal = 'equal';
    case Exact = 'exact';
    case Percentage = 'percentage';
    case Shares = 'shares';

    public function label(): string
    {
        return match ($this) {
            self::Equal => 'Equally',
            self::Exact => 'Exact amounts',
            self::Percentage => 'Percentages',
            self::Shares => 'Shares',
        };
    }
}
