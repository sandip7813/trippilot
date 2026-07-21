<?php

namespace App\Actions\Knowledge;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeIndexer;

class UpdateKnowledgeDocument
{
    public function __construct(private KnowledgeIndexer $indexer) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function __invoke(KnowledgeDocument $document, array $validated): KnowledgeDocument
    {
        $document->update([
            'title' => $validated['title'],
            'destinations' => KnowledgeDocument::normalizeDestinations(
                $this->parseDestinations($validated['destinations'] ?? ''),
            ),
            'content' => $validated['content'],
            'status' => KnowledgeDocumentStatus::from($validated['status']),
        ]);

        return $this->indexer->index($document);
    }

    /**
     * @return list<string>
     */
    private function parseDestinations(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map(strval(...), $value));
        }

        return array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) $value),
        )));
    }
}
