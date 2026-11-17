<?php

namespace App\Console\Commands;

use App\Actions\Credits\GrantSignupBonus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * One-time backfill for the AI credits feature: gives the welcome bonus to
 * verified users who signed up before credits existed. Users who already
 * have the bonus are skipped, so running it again is harmless.
 */
class GrantSignupBonusCommand extends Command
{
    protected $signature = 'trippilot:grant-signup-bonus';

    protected $description = 'Give the welcome credit bonus to existing verified users who have not received it';

    public function handle(GrantSignupBonus $grantSignupBonus): int
    {
        $granted = 0;

        User::query()
            ->whereNotNull('email_verified_at')
            ->where('role', UserRole::User)
            ->orderBy('id')
            ->each(function (User $user) use ($grantSignupBonus, &$granted): void {
                if ($grantSignupBonus($user) !== null) {
                    $granted++;
                }
            });

        $this->components->info("Welcome bonus given to {$granted} user(s).");

        return self::SUCCESS;
    }
}
