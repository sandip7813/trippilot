<?php

namespace App\Enums;

enum CreditOrderStatus: string
{
    case Created = 'created';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Awaiting payment',
            self::Paid => 'Paid',
            self::Refunded => 'Refunded',
        };
    }
}
