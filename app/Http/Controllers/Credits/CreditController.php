<?php

namespace App\Http\Controllers\Credits;

use App\Enums\CreditService;
use App\Http\Controllers\Controller;
use App\Models\CreditPack;
use App\Models\CreditTransaction;
use App\Models\Trip;
use App\Services\Credits\AiCreditCharger;
use App\Services\Credits\CreditCosts;
use App\Services\Credits\CreditPurchases;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    /**
     * Show how many credits each AI service uses.
     */
    public function costs(Request $request, CreditCosts $creditCosts, AiCreditCharger $creditCharger): Response
    {
        $costs = $creditCosts->all();

        return Inertia::render('credits/Costs', [
            ...$this->status($request, $creditCharger),
            'costs' => array_map(
                fn (CreditService $service): array => [
                    'service' => $service->value,
                    'label' => $service->label(),
                    'credits' => $costs[$service->value],
                ],
                CreditService::cases(),
            ),
        ]);
    }

    /**
     * Show the credit packs the user can buy.
     */
    public function buy(Request $request, AiCreditCharger $creditCharger, CreditPurchases $creditPurchases): Response
    {
        $unavailableReason = $creditPurchases->unavailableReason($request->user());

        return Inertia::render('credits/Buy', [
            ...$this->status($request, $creditCharger),
            'unavailable_reason' => $unavailableReason,
            'packs' => $unavailableReason === null
                ? $creditPurchases->packsOnSale()->map(fn (CreditPack $pack): array => [
                    'id' => $pack->id,
                    'name' => $pack->name,
                    'price' => intdiv($pack->price_paise, 100),
                    'price_paise' => $pack->price_paise,
                    'credits' => $pack->credits,
                    'is_featured' => $pack->is_featured,
                ])->values()->all()
                : [],
        ]);
    }

    /**
     * Show the credits added to and used from the user's balance.
     */
    public function history(Request $request, AiCreditCharger $creditCharger): Response
    {
        $transactions = $request->user()->creditTransactions()
            ->latest()
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $tripTitles = $this->tripTitles($transactions->getCollection()->pluck('trip_id')->filter()->unique()->values()->all());

        return Inertia::render('credits/History', [
            ...$this->status($request, $creditCharger),
            'transactions' => $transactions->through(fn (CreditTransaction $transaction): array => [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'label' => $transaction->service?->label() ?? $transaction->type->label(),
                'type_label' => $transaction->type->label(),
                'amount' => $transaction->amount,
                'balance_after' => $transaction->balance_after,
                'trip_title' => $transaction->trip_id !== null ? ($tripTitles[$transaction->trip_id] ?? null) : null,
                'note' => $transaction->note,
                'created_at' => $transaction->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * @return array{balance: int, charging_enabled: bool, is_exempt: bool}
     */
    private function status(Request $request, AiCreditCharger $creditCharger): array
    {
        return [
            'balance' => $request->user()->credit_balance,
            'charging_enabled' => $creditCharger->isEnabled(),
            'is_exempt' => $request->user()->isExemptFromCredits(),
        ];
    }

    /**
     * @param  list<string>  $tripIds
     * @return array<string, string>
     */
    private function tripTitles(array $tripIds): array
    {
        if ($tripIds === []) {
            return [];
        }

        return Trip::query()
            ->whereIn('id', $tripIds)
            ->get(['title'])
            ->mapWithKeys(fn (Trip $trip): array => [(string) $trip->id => (string) $trip->title])
            ->all();
    }
}
