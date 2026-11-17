<?php

use App\Models\ContentPage;
use App\Models\CreditPack;
use App\Models\User;

test('policy pages are public and fill in the business details', function (string $routeName, string $slug) {
    config([
        'trippilot.legal.business_name' => 'Asha Travels',
        'trippilot.legal.support_email' => 'help@example.com',
        'trippilot.legal.address' => '12 Park Street, Kolkata',
        'trippilot.legal.jurisdiction' => 'Kolkata, West Bengal',
    ]);

    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('legal/ContentPage')
            ->where('page.slug', $slug)
            ->where('page.html', fn (string $html) => str_contains($html, 'Asha Travels') && ! str_contains($html, '{business_name}'))
            ->where('legal.support_email', 'help@example.com')
            ->where('legal.address', '12 Park Street, Kolkata')
            ->where('edit_url', null));
})->with([
    'terms' => ['terms', 'terms'],
    'privacy' => ['privacy', 'privacy'],
    'refund policy' => ['refund-policy', 'refund-policy'],
]);

test('the pricing page lists packs on sale and what each AI feature costs', function () {
    CreditPack::query()->delete();
    CreditPack::factory()->create(['name' => 'Starter', 'price_paise' => 4900, 'credits' => 50, 'sort_order' => 1]);
    CreditPack::factory()->inactive()->create(['name' => 'Retired']);
    config(['trippilot.credits.signup_bonus' => 30]);

    $this->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('legal/Pricing')
            ->has('packs', 1)
            ->where('packs.0.name', 'Starter')
            ->where('packs.0.price_paise', 4900)
            ->where('signup_bonus', 30)
            ->where('costs.0.service', 'itinerary_generation')
            ->where('costs.0.credits', 12));
});

test('signed-in users can also open the policy pages', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('refund-policy'))
        ->assertOk();
});

test('published custom pages are public and drafts are hidden from visitors', function () {
    $about = ContentPage::factory()->create(['slug' => 'about-us', 'body' => 'We plan **trips**.']);
    $draft = ContentPage::factory()->draft()->create();

    $this->get(route('pages.show', $about))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('legal/ContentPage')
            ->where('page.html', "<p>We plan <strong>trips</strong>.</p>\n"));

    $this->get(route('pages.show', $draft))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('pages.show', $draft))->assertNotFound();
    $this->actingAs(User::factory()->admin()->create())->get(route('pages.show', $draft))->assertOk();
});

test('a policy page opened through the generic address redirects to its own address', function () {
    $this->get('/pages/terms')->assertRedirect('/terms');
});

test('raw HTML and script links in page content are not rendered', function () {
    $page = ContentPage::factory()->create([
        'body' => "<script>alert(1)</script>\n\n[click](javascript:alert(1)) <img src=x onerror=alert(1)>",
    ]);

    $this->get(route('pages.show', $page))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia->where('page.html', fn (string $html) => ! str_contains($html, '<script')
            && ! str_contains($html, 'javascript:')
            && ! str_contains($html, 'onerror')));
});

test('every page shares the published footer pages in order', function () {
    ContentPage::factory()->create(['title' => 'About us', 'slug' => 'about-us', 'sort_order' => 0]);
    ContentPage::factory()->draft()->create(['title' => 'Hidden draft']);
    ContentPage::factory()->create(['title' => 'Not in footer', 'show_in_footer' => false]);

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page->where('footerPages', [
            ['title' => 'About us', 'href' => '/pages/about-us'],
            ['title' => 'Terms & Conditions', 'href' => '/terms'],
            ['title' => 'Privacy Policy', 'href' => '/privacy'],
            ['title' => 'Refund & Cancellation Policy', 'href' => '/refund-policy'],
        ]));
});
