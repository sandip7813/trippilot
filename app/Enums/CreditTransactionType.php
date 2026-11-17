<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case SignupBonus = 'signup_bonus';
    case AdminGrant = 'admin_grant';
    case Purchase = 'purchase';
    case PurchaseReversal = 'purchase_reversal';
    case Spend = 'spend';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::SignupBonus => 'Welcome bonus',
            self::AdminGrant => 'Added by TripPilot',
            self::Purchase => 'Credits purchased',
            self::PurchaseReversal => 'Payment refunded',
            self::Spend => 'Used',
            self::Refund => 'Refunded',
        };
    }
}
