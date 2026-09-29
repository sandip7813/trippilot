<?php

namespace App\Enums;

enum ExpenseSheetVisibility: string
{
    case Private = 'private';
    case Shared = 'shared';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private (owner only)',
            self::Shared => 'Shared with the trip',
        };
    }
}
