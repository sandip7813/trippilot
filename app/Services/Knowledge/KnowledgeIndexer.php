<?php

namespace App\Services\Knowledge;

use App\Contracts\Ai\EmbeddingService;
use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;

class KnowledgeIndexer
{
    public function __construct(
        private DocumentChunker $chunker,
        private EmbeddingService $embeddingService,
    ) {}

    public function index(KnowledgeDocument $document): KnowledgeDocument
    {
        KnowledgeChunk::query()
            ->where('document_id', (string) $document->id)
            ->delete();

        if ($document->status !== KnowledgeDocumentStatus::Published) {
            $document->update(['chunk_count' => 0]);

            return $document->fresh();
        }

        $chunks = $this->chunker->chunk($document->content);
        $destinations = KnowledgeDocument::normalizeDestinations($document->getAttribute('destinations'));

        foreach ($chunks as $index => $chunkContent) {
            KnowledgeChunk::query()->create([
                'document_id' => (string) $document->id,
                'document_title' => $document->title,
                'chunk_index' => $index,
                'content' => $chunkContent,
                'embedding' => $this->embeddingService->embed($chunkContent),
                'destinations' => $destinations,
            ]);
        }

        $document->update(['chunk_count' => count($chunks)]);

        return $document->fresh();
    }

    public function deleteForDocument(KnowledgeDocument $document): void
    {
        KnowledgeChunk::query()
            ->where('document_id', (string) $document->id)
            ->delete();
    }
}
