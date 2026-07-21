<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Knowledge\DeleteKnowledgeDocument;
use App\Actions\Knowledge\StoreKnowledgeDocument;
use App\Actions\Knowledge\UpdateKnowledgeDocument;
use App\Enums\KnowledgeDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreKnowledgeDocumentRequest;
use App\Http\Requests\Admin\UpdateKnowledgeDocumentRequest;
use App\Models\KnowledgeDocument;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeDocumentController extends Controller
{
    public function index(): Response
    {
        $documents = KnowledgeDocument::query()
            ->orderByDesc('updated_at')
            ->get()
            ->map->toFrontend();

        return Inertia::render('admin/knowledge/Index', [
            'documents' => $documents,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/knowledge/Create', [
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function store(
        StoreKnowledgeDocumentRequest $request,
        StoreKnowledgeDocument $storeKnowledgeDocument,
    ): RedirectResponse {
        $document = $storeKnowledgeDocument($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Knowledge document created and indexed.'),
        ]);

        return to_route('admin.knowledge.edit', $document);
    }

    public function edit(KnowledgeDocument $knowledge): Response
    {
        return Inertia::render('admin/knowledge/Edit', [
            'document' => $knowledge->toFrontend(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function update(
        UpdateKnowledgeDocumentRequest $request,
        KnowledgeDocument $knowledge,
        UpdateKnowledgeDocument $updateKnowledgeDocument,
    ): RedirectResponse {
        $updateKnowledgeDocument($knowledge, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Knowledge document updated and re-indexed.'),
        ]);

        return to_route('admin.knowledge.edit', $knowledge);
    }

    public function destroy(
        KnowledgeDocument $knowledge,
        DeleteKnowledgeDocument $deleteKnowledgeDocument,
    ): RedirectResponse {
        $deleteKnowledgeDocument($knowledge);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Knowledge document deleted.'),
        ]);

        return to_route('admin.knowledge.index');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (KnowledgeDocumentStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            KnowledgeDocumentStatus::cases(),
        );
    }
}
