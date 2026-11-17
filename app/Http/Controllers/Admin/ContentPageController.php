<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContentPageRequest;
use App\Http\Requests\Admin\UpdateContentPageRequest;
use App\Models\ContentPage;
use App\Services\ContentPages\ContentPageRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets admins and super admins edit the public content pages (Terms,
 * Privacy, Refund policy) and add their own, such as About us.
 */
class ContentPageController extends Controller
{
    public function __construct(private ContentPageRenderer $renderer) {}

    public function index(): Response
    {
        return Inertia::render('admin/content-pages/Index', [
            'pages' => ContentPage::query()
                ->with('updatedBy:id,first_name,last_name')
                ->ordered()
                ->get()
                ->map(fn (ContentPage $page): array => [
                    ...$this->present($page),
                    'updated_by' => $page->updatedBy?->name,
                ])
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/content-pages/Create', [
            'placeholders' => $this->renderer->placeholders(),
            'next_sort_order' => (int) ContentPage::query()->max('sort_order') + 1,
        ]);
    }

    public function store(StoreContentPageRequest $request): RedirectResponse
    {
        $page = ContentPage::query()->create([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page created.')]);

        return to_route('admin.content-pages.edit', $page);
    }

    public function edit(ContentPage $contentPage): Response
    {
        return Inertia::render('admin/content-pages/Edit', [
            'page' => [
                ...$this->present($contentPage),
                'description' => $contentPage->description,
                'body' => $contentPage->body,
            ],
            'placeholders' => $this->renderer->placeholders(),
        ]);
    }

    public function update(UpdateContentPageRequest $request, ContentPage $contentPage): RedirectResponse
    {
        $contentPage->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page saved.')]);

        return to_route('admin.content-pages.edit', $contentPage);
    }

    public function destroy(ContentPage $contentPage): RedirectResponse
    {
        abort_if($contentPage->is_system, 403, __('Policy pages cannot be deleted.'));

        $contentPage->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page deleted.')]);

        return to_route('admin.content-pages.index');
    }

    /**
     * Renders unsaved Markdown for the editor's Preview tab.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:100000'],
        ]);

        return response()->json([
            'html' => $this->renderer->toHtml((string) ($validated['body'] ?? '')),
        ]);
    }

    /**
     * @return array{id: int, slug: string, title: string, url: string, is_system: bool, is_published: bool, show_in_footer: bool, sort_order: int, updated_at: string|null}
     */
    private function present(ContentPage $page): array
    {
        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'url' => $page->url(),
            'is_system' => $page->is_system,
            'is_published' => $page->is_published,
            'show_in_footer' => $page->show_in_footer,
            'sort_order' => $page->sort_order,
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];
    }
}
