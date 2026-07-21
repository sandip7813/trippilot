<?php

use App\Services\Knowledge\DocumentChunker;
use Tests\TestCase;

uses(TestCase::class);

test('document chunker splits long content into multiple chunks', function () {
    config([
        'trippilot.rag.chunk_max_characters' => 200,
        'trippilot.rag.chunk_overlap_characters' => 0,
    ]);

    $chunker = new DocumentChunker;

    $content = implode("\n\n", array_fill(0, 6, str_repeat('Goa beach tip. ', 8)));

    $chunks = $chunker->chunk($content);

    expect($chunks)->not->toBeEmpty()
        ->and(count($chunks))->toBeGreaterThan(1)
        ->and(strlen($chunks[0]))->toBeLessThanOrEqual(200);
});

test('document chunker returns empty array for blank content', function () {
    $chunker = new DocumentChunker;

    expect($chunker->chunk('   '))->toBe([]);
});
