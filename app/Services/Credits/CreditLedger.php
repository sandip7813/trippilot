<?php

namespace App\Services\Credits;

use App\Enums\CreditService;
use App\Enums\CreditTransactionType;
use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditOrder;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only place a user's credit balance changes. Every change locks the
 * user's row, writes one ledger entry, and updates the cached balance in the
 * same transaction, so concurrent requests can never overspend and the
 * balance always equals the sum of the user's ledger entries.
 */
class CreditLedger
{
    /**
     * @throws InsufficientCreditsException
     */
    public function spend(User $user, CreditService $service, int $credits, ?string $tripId = null): CreditTransaction
    {
        return $this->apply($user, function (User $lockedUser) use ($service, $credits, $tripId): array {
            if ($lockedUser->credit_balance < $credits) {
                throw new InsufficientCreditsException($credits, $lockedUser->credit_balance);
            }

            return [
                'type' => CreditTransactionType::Spend,
                'amount' => -$credits,
                'service' => $service,
                'trip_id' => $tripId,
            ];
        });
    }

    /**
     * Return the credits taken by a spend entry. Refunding the same entry
     * twice returns the existing refund instead of adding credits again.
     */
    public function refund(CreditTransaction $spend): CreditTransaction
    {
        $user = User::query()->findOrFail($spend->user_id);

        return DB::transaction(function () use ($user, $spend): CreditTransaction {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $existingRefund = CreditTransaction::query()
                ->where('related_transaction_id', $spend->id)
                ->where('type', CreditTransactionType::Refund)
                ->first();

            if ($existingRefund !== null) {
                return $existingRefund;
            }

            return $this->record($lockedUser, [
                'type' => CreditTransactionType::Refund,
                'amount' => abs($spend->amount),
                'service' => $spend->service,
                'trip_id' => $spend->trip_id,
                'related_transaction_id' => $spend->id,
            ]);
        });
    }

    public function grant(User $user, int $credits, ?User $grantedBy = null, ?string $note = null): CreditTransaction
    {
        return $this->apply($user, fn (): array => [
            'type' => CreditTransactionType::AdminGrant,
            'amount' => $credits,
            'granted_by' => $grantedBy?->id,
            'note' => $note,
        ]);
    }

    /**
     * Add the credits of a paid order. Callers make sure each order is only
     * completed once.
     */
    public function purchase(User $user, CreditOrder $order): CreditTransaction
    {
        return $this->apply($user, fn (): array => [
            'type' => CreditTransactionType::Purchase,
            'amount' => $order->credits,
            'credit_order_id' => $order->id,
            'note' => $order->pack_name,
        ]);
    }

    /**
     * Take back up to $credits after a payment refund. Credits the user has
     * already spent are not clawed back, so the balance never goes negative.
     * Returns null when there is nothing left to take back.
     */
    public function reversePurchase(User $user, CreditOrder $order, int $credits): ?CreditTransaction
    {
        return $this->apply($user, function (User $lockedUser) use ($order, $credits): ?array {
            $reversible = min($credits, $lockedUser->credit_balance);

            if ($reversible <= 0) {
                return null;
            }

            return [
                'type' => CreditTransactionType::PurchaseReversal,
                'amount' => -$reversible,
                'credit_order_id' => $order->id,
                'note' => $order->pack_name,
            ];
        });
    }

    /**
     * Give the one-time welcome bonus. Returns null when the user already
     * received it.
     */
    public function grantSignupBonus(User $user, int $credits): ?CreditTransaction
    {
        return $this->apply($user, function (User $lockedUser) use ($credits): ?array {
            $alreadyGranted = $lockedUser->creditTransactions()
                ->where('type', CreditTransactionType::SignupBonus)
                ->exists();

            if ($alreadyGranted) {
                return null;
            }

            return [
                'type' => CreditTransactionType::SignupBonus,
                'amount' => $credits,
            ];
        });
    }

    /**
     * Lock the user, let $resolveEntry decide the ledger entry (or return null
     * for no change), then write it and keep the given model's balance in sync.
     *
     * @param  callable(User): (array<string, mixed>|null)  $resolveEntry
     */
    private function apply(User $user, callable $resolveEntry): ?CreditTransaction
    {
        $transaction = DB::transaction(function () use ($user, $resolveEntry): ?CreditTransaction {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $entry = $resolveEntry($lockedUser);

            return $entry === null ? null : $this->record($lockedUser, $entry);
        });

        if ($transaction !== null) {
            $user->forceFill(['credit_balance' => $transaction->balance_after])->syncOriginalAttribute('credit_balance');
        }

        return $transaction;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function record(User $lockedUser, array $entry): CreditTransaction
    {
        $balanceAfter = $lockedUser->credit_balance + (int) $entry['amount'];

        $lockedUser->forceFill(['credit_balance' => $balanceAfter])->save();

        return $lockedUser->creditTransactions()->create([
            ...$entry,
            'balance_after' => $balanceAfter,
        ]);
    }
}
