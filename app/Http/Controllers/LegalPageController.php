<?php

namespace App\Http\Controllers;

use App\Enums\CreditService;
use App\Models\ContentPage;
use App\Models\CreditPack;
use App\Services\ContentPages\ContentPageRenderer;
use App\Services\Credits\CreditCosts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public policy, pricing and content pages. Payment providers review the
 * policy pages before activating an account, so they are reachable without
 * signing in. Page text is edited by admins under Admin → Content pages.
 */
class LegalPageController extends Controller
{
    public function __construct(private ContentPageRenderer $renderer) {}

    public function terms(Request $request): Response
    {
        return $this->renderPage($request, $this->systemPage('terms'));
    }

    public function privacy(Request $request): Response
    {
        return $this->renderPage($request, $this->systemPage('privacy'));
    }

    public function refundPolicy(Request $request): Response
    {
        return $this->renderPage($request, $this->systemPage('refund-policy'));
    }

    /**
     * Pages admins add themselves, served at /pages/{slug}. Drafts are only
     * visible to admins, so they can check a page before publishing it.
     */
    public function show(Request $request, ContentPage $contentPage): Response|RedirectResponse
    {
        if ($contentPage->is_system) {
            return redirect($contentPage->url(), 301);
        }

        abort_unless($contentPage->is_published || $request->user()?->isAdmin(), 404);

        return $this->renderPage($request, $contentPage);
    }

    public function pricing(CreditCosts $creditCosts): Response
    {
        $costs = $creditCosts->all();

        return Inertia::render('legal/Pricing', [
            'legal' => $this->renderer->businessDetails(),
            'signup_bonus' => (int) config('trippilot.credits.signup_bonus', 0),
            'packs' => CreditPack::query()->active()->ordered()->get()->map(fn (CreditPack $pack): array => [
                'id' => $pack->id,
                'name' => $pack->name,
                'price_paise' => $pack->price_paise,
                'credits' => $pack->credits,
                'is_featured' => $pack->is_featured,
            ])->all(),
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

    private function systemPage(string $slug): ContentPage
    {
        return ContentPage::query()->where('slug', $slug)->firstOrFail();
    }

    private function renderPage(Request $request, ContentPage $page): Response
    {
        return Inertia::render('legal/ContentPage', [
            'legal' => $this->renderer->businessDetails(),
            'page' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'description' => $page->description,
                'html' => $this->renderer->toHtml($page->body),
                'is_published' => $page->is_published,
                'updated_at' => $page->updated_at?->toIso8601String(),
            ],
            'edit_url' => $request->user()?->isAdmin()
                ? route('admin.content-pages.edit', $page, absolute: false)
                : null,
        ]);
    }
}
