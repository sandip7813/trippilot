<?php

namespace App\Actions\Knowledge;

use App\Models\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeIndexer;

class DeleteKnowledgeDocument
{
    public function __construct(private KnowledgeIndexer $indexer) {}

    public function __invoke(KnowledgeDocument $document): void
    {
        $this->indexer->deleteForDocument($document);
        $document->delete();
    }
}
