<?php

namespace App\Data\Knowledge;

readonly class RetrievedChunk
{
    public function __construct(
        public string $documentId,
        public string $documentTitle,
        public string $content,
        public float $score,
    ) {}
}
