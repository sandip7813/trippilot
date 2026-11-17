<?php

namespace App\Http\Controllers\Admin\Super;

use App\Enums\CreditService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCreditSettingsRequest;
use App\Models\CreditPack;
use App\Services\Admin\PlatformSettings;
use App\Services\Credits\AiCreditCharger;
use App\Services\Credits\CreditCosts;
use App\Services\Payments\RazorpayClient;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CreditSettingsController extends Controller
{
    /**
     * Show the credit cost of each AI service and whether credits are charged.
     */
    public function show(CreditCosts $creditCosts, AiCreditCharger $creditCharger, RazorpayClient $razorpay): Response
    {
        $costs = $creditCosts->all();

        return Inertia::render('admin/super/Credits', [
            'enabled' => $creditCharger->isEnabled(),
            'signup_bonus' => (int) config('trippilot.credits.signup_bonus', 0),
            'services' => array_map(
                fn (CreditService $service): array => [
                    'service' => $service->value,
                    'label' => $service->label(),
                    'credits' => $costs[$service->value],
                    'default_credits' => $service->defaultCredits(),
                ],
                CreditService::cases(),
            ),
            'payments_configured' => $razorpay->isConfigured(),
            'packs' => CreditPack::query()->ordered()->get()->map(fn (CreditPack $pack): array => [
                'id' => $pack->id,
                'name' => $pack->name,
                'price' => intdiv($pack->price_paise, 100),
                'credits' => $pack->credits,
                'is_featured' => $pack->is_featured,
                'is_active' => $pack->is_active,
            ])->all(),
        ]);
    }

    public function update(
        UpdateCreditSettingsRequest $request,
        CreditCosts $creditCosts,
        PlatformSettings $platformSettings,
    ): RedirectResponse {
        $platformSettings->set('credits.enabled', $request->boolean('enabled'));

        $creditCosts->update(
            array_map(intval(...), $request->validated('costs')),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Credit settings saved.'),
        ]);

        return back();
    }
}
