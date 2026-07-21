<?php

use App\Contracts\Ai\EmbeddingService;
use App\Enums\KnowledgeDocumentStatus;
use App\Enums\TripType;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\Trip;
use App\Services\Knowledge\RagService;
use App\Services\Trips\TripAiContextBuilder;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (! extension_loaded('mongodb')) {
        test()->markTestSkipped('MongoDB PHP extension is not installed.');
    }

    try {
        KnowledgeDocument::query()->where('_id', '!=', null)->limit(1)->get();
    } catch (Throwable $exception) {
        test()->markTestSkipped('MongoDB is not available: '.$exception->getMessage());
    }

    KnowledgeChunk::query()->whereNotNull('_id')->delete();
    KnowledgeDocument::query()->whereNotNull('_id')->delete();
});

test('rag service retrieves destination relevant chunks', function () {
    config(['integrations.ai.drivers.gemini.api_key' => 'test-key']);

    $this->mock(EmbeddingService::class, function ($mock): void {
        $mock->shouldReceive('embed')
            ->andReturnUsing(function (string $text): array {
                if (str_contains(strtolower($text), 'goa')) {
                    return [1.0, 0.0, 0.0];
                }

                if (str_contains(strtolower($text), 'delhi')) {
                    return [0.0, 1.0, 0.0];
                }

                return [0.0, 0.0, 1.0];
            });
    });

    $goaDocument = KnowledgeDocument::factory()->create([
        'title' => 'Goa guide',
        'destinations' => ['goa'],
        'status' => KnowledgeDocumentStatus::Published,
    ]);

    KnowledgeDocument::factory()->create([
        'title' => 'Delhi guide',
        'destinations' => ['delhi'],
        'status' => KnowledgeDocumentStatus::Published,
    ]);

    KnowledgeChunk::factory()->create([
        'document_id' => (string) $goaDocument->id,
        'document_title' => 'Goa guide',
        'content' => 'Monsoon beach safety in Goa.',
        'embedding' => [1.0, 0.0, 0.0],
        'destinations' => ['goa'],
    ]);

    KnowledgeChunk::factory()->create([
        'document_id' => 'delhi-doc',
        'document_title' => 'Delhi guide',
        'content' => 'Metro tips for Delhi sightseeing.',
        'embedding' => [0.0, 1.0, 0.0],
        'destinations' => ['delhi'],
    ]);

    $result = app(RagService::class)->retrieve('Goa monsoon beaches', ['goa']);

    expect($result['context'])->toContain('Goa guide')
        ->and($result['context'])->toContain('Monsoon beach safety in Goa.')
        ->and($result['context'])->not->toContain('Metro tips for Delhi sightseeing.')
        ->and($result['sources'])->toHaveCount(1);
});

test('trip context builder includes rag context when knowledge exists', function () {
    config(['integrations.ai.drivers.gemini.api_key' => 'test-key']);

    $this->mock(EmbeddingService::class, function ($mock): void {
        $mock->shouldReceive('embed')->andReturn([1.0, 0.0, 0.0]);
    });

    $document = KnowledgeDocument::factory()->create([
        'title' => 'Goa guide',
        'destinations' => ['goa'],
        'status' => KnowledgeDocumentStatus::Published,
    ]);

    KnowledgeChunk::factory()->create([
        'document_id' => (string) $document->id,
        'document_title' => 'Goa guide',
        'content' => 'Try fish thali and carry rain gear in monsoon.',
        'embedding' => [1.0, 0.0, 0.0],
        'destinations' => ['goa'],
    ]);

    $trip = new Trip([
        'type' => TripType::Vacation,
        'title' => 'Goa getaway',
        'destination' => [
            'label' => 'Goa, India',
            'lat' => null,
            'lng' => null,
        ],
    ]);

    $context = app(TripAiContextBuilder::class)->build($trip);

    expect($context['rag_context'])->toContain('fish thali')
        ->and($context['rag_sources'])->not->toBeEmpty();
});
