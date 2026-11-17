<?php

namespace App\Http\Middleware;

use App\Http\Controllers\NotificationController;
use App\Models\ContentPage;
use App\Models\User;
use App\Services\Credits\AiCreditCharger;
use App\Services\Credits\CreditCosts;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        private AiCreditCharger $creditCharger,
        private CreditCosts $creditCosts,
    ) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'notifications' => [
                'unread' => fn (): int => $request->user()?->unreadNotifications()->count() ?? 0,
                'recent' => fn (): array => $request->user()?->notifications()
                    ->latest()
                    ->limit(6)
                    ->get()
                    ->map(fn (DatabaseNotification $notification): array => NotificationController::present($notification))
                    ->all() ?? [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'brand' => [
                'logo' => config('trippilot.logo'),
            ],
            'integrations' => [
                'locationSearchEnabled' => filled(config('integrations.maps.drivers.geoapify.api_key')),
            ],
            'currency' => [
                'code' => config('trippilot.currency', 'INR'),
                'locale' => config('trippilot.currency_locale', 'en-IN'),
            ],
            'credits' => fn (): ?array => $this->creditsFor($request->user()),
            'footerPages' => fn (): array => ContentPage::footerLinks(),
        ];
    }

    /**
     * The signed-in user's credit balance and the cost of each AI service, or
     * null when the user does not spend credits (guests, admins, or credits
     * switched off), which hides every credit label in the UI.
     *
     * @return array{balance: int, costs: array<string, int>}|null
     */
    private function creditsFor(?User $user): ?array
    {
        if ($user === null || ! $this->creditCharger->appliesTo($user)) {
            return null;
        }

        return [
            'balance' => $user->credit_balance,
            'costs' => $this->creditCosts->all(),
        ];
    }
}
