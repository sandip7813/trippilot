<?php

namespace App\Enums;

enum OpenTripCostModel: string
{
    case CostSharing = 'cost_sharing';
    case FixedPrice = 'fixed_price';
    case PayOwn = 'pay_own';

    public function label(): string
    {
        return match ($this) {
            self::CostSharing => 'Cost sharing',
            self::FixedPrice => 'Fixed price',
            self::PayOwn => 'Everyone pays their own',
        };
    }

    /**
     * Whether this model carries a per-person amount to display.
     */
    public function hasAmount(): bool
    {
        return $this !== self::PayOwn;
    }
}
