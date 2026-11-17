<?php

use App\Models\ContentPage;
use App\Models\User;

function termsPage(): ContentPage
{
    return ContentPage::query()->where('slug', 'terms')->firstOrFail();
}

test('admins and super admins can open the content pages admin', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())
        ->get(route('admin.content-pages.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/content-pages/Index')
            ->has('pages', 3));
})->with(['admin', 'superAdmin']);

test('the content pages list shows who last edited each page', function () {
    $admin = User::factory()->admin()->create(['first_name' => 'Asha', 'last_name' => 'Rao']);
    termsPage()->update(['updated_by' => $admin->id]);

    $this->actingAs($admin)
        ->get(route('admin.content-pages.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pages.0.updated_by', 'Asha Rao'));
});

test('regular users cannot manage content pages', function () {
    $user = User::factory()->create();
    $page = termsPage();

    $this->actingAs($user)->get(route('admin.content-pages.index'))->assertForbidden();
    $this->actingAs($user)->put(route('admin.content-pages.update', $page), [
        'title' => 'Hacked',
        'body' => 'Hacked',
        'sort_order' => 1,
    ])->assertForbidden();

    expect($page->fresh()->title)->toBe('Terms & Conditions');
});

test('admins can edit a policy page and the public page shows the new text', function () {
    $admin = User::factory()->admin()->create();
    $page = termsPage();

    $this->actingAs($admin)
        ->put(route('admin.content-pages.update', $page), [
            'title' => 'Terms of Use',
            'slug' => 'something-else',
            'description' => 'Updated rules.',
            'body' => "## Rules\n\nBe kind to {business_name}.",
            'is_published' => '0',
            'show_in_footer' => '1',
            'sort_order' => 1,
        ])
        ->assertRedirect(route('admin.content-pages.edit', $page));

    $page->refresh();

    expect($page->title)->toBe('Terms of Use')
        ->and($page->slug)->toBe('terms')
        ->and($page->is_published)->toBeTrue()
        ->and($page->updated_by)->toBe($admin->id);

    config(['trippilot.legal.business_name' => 'Asha Travels']);

    $this->get(route('terms'))
        ->assertInertia(fn ($inertia) => $inertia
            ->where('page.title', 'Terms of Use')
            ->where('page.html', "<h2>Rules</h2>\n<p>Be kind to Asha Travels.</p>\n"));
});

test('admins can add a page that appears in the footer', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.content-pages.store'), [
            'title' => 'About us',
            'slug' => 'about-us',
            'body' => 'Hello.',
            'is_published' => '1',
            'show_in_footer' => '1',
            'sort_order' => 4,
        ])
        ->assertRedirect();

    $page = ContentPage::query()->where('slug', 'about-us')->firstOrFail();

    expect($page->is_system)->toBeFalse()
        ->and($page->updated_by)->toBe($admin->id);

    $this->get(route('home'))
        ->assertInertia(fn ($inertia) => $inertia->where('footerPages.3', ['title' => 'About us', 'href' => '/pages/about-us']));
});

test('new pages need a unique, URL-safe address', function (array $input, string $errorField) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.content-pages.store'), [
            'title' => 'About us',
            'slug' => 'about-us',
            'body' => 'Hello.',
            'sort_order' => 4,
            ...$input,
        ])
        ->assertSessionHasErrors($errorField);
})->with([
    'taken slug' => [['slug' => 'terms'], 'slug'],
    'spaces and capitals' => [['slug' => 'About Us'], 'slug'],
    'missing body' => [['body' => ''], 'body'],
]);

test('custom pages can be deleted but policy pages cannot', function () {
    $admin = User::factory()->admin()->create();
    $about = ContentPage::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.content-pages.destroy', $about))
        ->assertRedirect(route('admin.content-pages.index'));

    $this->actingAs($admin)
        ->delete(route('admin.content-pages.destroy', termsPage()))
        ->assertForbidden();

    expect(ContentPage::query()->whereKey($about->id)->exists())->toBeFalse()
        ->and(termsPage()->exists)->toBeTrue();
});

test('the editor preview renders markdown and fills in placeholders, even when escaped', function () {
    config(['trippilot.legal.support_email' => 'help@example.com']);

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('admin.content-pages.preview'), ['body' => 'Write to {support\_email}'])
        ->assertOk()
        ->assertExactJson(['html' => "<p>Write to <a href=\"mailto:help@example.com\">help@example.com</a></p>\n"]);
});

test('admins see an edit link on the public page', function () {
    $page = termsPage();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('terms'))
        ->assertInertia(fn ($inertia) => $inertia->where('edit_url', route('admin.content-pages.edit', $page, absolute: false)));
});
