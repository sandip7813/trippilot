<?php

namespace App\Services\Credits;

use App\Enums\CreditService;
use App\Exceptions\InsufficientCreditsException;
use App\Models\User;
use Closure;
use Throwable;

/**
 * Charges a user for one AI action: credits are taken before the AI call and
 * returned automatically if the call fails. Admins, super admins, and
 * everyone while credits are switched off run the action for free.
 */
class AiCreditCharger
{
    public function __construct(
        private CreditLedger $ledger,
        private CreditCosts $costs,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('trippilot.credits.enabled', false);
    }

    public function appliesTo(User $user): bool
    {
        return $this->isEnabled() && ! $user->isExemptFromCredits();
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $action
     * @return TResult
     *
     * @throws InsufficientCreditsException
     */
    public function charge(User $user, CreditService $service, Closure $action, ?string $tripId = null): mixed
    {
        $credits = $this->costs->for($service);

        if (! $this->appliesTo($user) || $credits <= 0) {
            return $action();
        }

        $spend = $this->ledger->spend($user, $service, $credits, $tripId);

        try {
            return $action();
        } catch (Throwable $exception) {
            $this->ledger->refund($spend);

            throw $exception;
        }
    }
}
