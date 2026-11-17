<?php

namespace App\Actions\Credits;

use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\Credits\CreditLedger;

/**
 * Gives a verified user their one-time welcome credits. Safe to call more
 * than once: users who already received the bonus, unverified users, and
 * admins (who never spend credits) are skipped.
 */
class GrantSignupBonus
{
    public function __construct(private CreditLedger $ledger) {}

    public function __invoke(User $user): ?CreditTransaction
    {
        $credits = (int) config('trippilot.credits.signup_bonus', 0);

        if ($credits <= 0 || $user->email_verified_at === null || $user->isExemptFromCredits()) {
            return null;
        }

        return $this->ledger->grantSignupBonus($user, $credits);
    }
}
