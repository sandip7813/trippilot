<?php

namespace App\Services\Credits;

use App\Models\CreditPack;
use App\Models\User;
use App\Services\Payments\RazorpayClient;
use Illuminate\Database\Eloquent\Collection;

/**
 * Decides who can buy credit packs and which packs are on sale.
 */
class CreditPurchases
{
    public function __construct(
        private RazorpayClient $razorpay,
        private AiCreditCharger $creditCharger,
    ) {}

    /**
     * Packs can be bought once Razorpay is set up and credits are charged.
     * Admins never spend credits, so they never buy them.
     */
    public function isAvailableFor(User $user): bool
    {
        return $this->razorpay->isConfigured() && $this->creditCharger->appliesTo($user);
    }

    /**
     * Explain to the user why packs cannot be bought, or null when they can.
     */
    public function unavailableReason(User $user): ?string
    {
        return match (true) {
            $user->isExemptFromCredits() => __('As an admin, you use AI features without spending credits, so there is nothing to buy.'),
            ! $this->creditCharger->isEnabled() => __('AI features are free for now, so you do not need to buy credits yet.'),
            ! $this->razorpay->isConfigured() => __('Buying credits is not available yet. Please check back soon.'),
            default => null,
        };
    }

    /**
     * @return Collection<int, CreditPack>
     */
    public function packsOnSale(): Collection
    {
        return CreditPack::query()->active()->ordered()->get();
    }
}
