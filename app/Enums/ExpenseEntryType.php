<?php

namespace App\Enums;

enum ExpenseEntryType: string
{
    case Payment = 'payment';
    case Refund = 'refund';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Payment => 'Payment',
            self::Refund => 'Refund',
            self::Transfer => 'Personal payment',
        };
    }

    /**
     * Refunds reverse the money flow of a payment.
     */
    public function sign(): int
    {
        return $this === self::Refund ? -1 : 1;
    }

    public function countsTowardSpend(): bool
    {
        return $this !== self::Transfer;
    }
}
