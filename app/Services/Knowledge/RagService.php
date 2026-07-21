<?php

namespace App\Services\Knowledge;

use App\Contracts\Ai\EmbeddingService;
use App\Data\Knowledge\RetrievedChunk;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Arr;

class RagService
{
    public function __construct(private EmbeddingService $embeddingService) {}

    /**
     * @param  list<string>  $destinations
     * @return array{
     *     context: string,
     *     sources: list<array{document_id: string, title: string, score: float}>,
     *     chunks: list<RetrievedChunk>
     * }
     */
    public function retrieve(string $query, array $destinations = []): array
    {
        $query = trim($query);

        if ($query === '' || ! filled(config('integrations.ai.drivers.gemini.api_key'))) {
            return $this->emptyResult();
        }

        $publishedDocumentIds = KnowledgeDocument::query()
            ->published()
            ->get()
            ->map(fn (KnowledgeDocument $document): string => (string) $document->id)
            ->all();

        if ($publishedDocumentIds === []) {
            return $this->emptyResult();
        }

        $normalizedDestinations = KnowledgeDocument::normalizeDestinations($destinations);

        $candidates = KnowledgeChunk::query()
            ->whereIn('document_id', $publishedDocumentIds)
            ->get();

        if ($normalizedDestinations !== []) {
            $candidates = $candidates->filter(function (KnowledgeChunk $chunk) use ($normalizedDestinations): bool {
                $chunkDestinations = is_array($chunk->destinations)
                    ? KnowledgeDocument::normalizeDestinations($chunk->destinations)
                    : [];

                return array_intersect($normalizedDestinations, $chunkDestinations) !== [];
            })->values();
        }

        if ($candidates->isEmpty()) {
            return $this->emptyResult();
        }

        $queryEmbedding = $this->embeddingService->embed($query);
        $topK = max(1, (int) config('trippilot.rag.top_k', 5));
        $minimumScore = (float) config('trippilot.rag.minimum_score', 0.2);

        $scored = $candidates
            ->map(function (KnowledgeChunk $chunk) use ($queryEmbedding): RetrievedChunk {
                $embedding = is_array($chunk->embedding) ? $chunk->embedding : [];

                return new RetrievedChunk(
                    documentId: (string) $chunk->document_id,
                    documentTitle: (string) $chunk->document_title,
                    content: (string) $chunk->content,
                    score: VectorSimilarity::cosine($queryEmbedding, $embedding),
                );
            })
            ->filter(fn (RetrievedChunk $chunk): bool => $chunk->score >= $minimumScore)
            ->sortByDesc(fn (RetrievedChunk $chunk): float => $chunk->score)
            ->take($topK)
            ->values();

        if ($scored->isEmpty()) {
            return $this->emptyResult();
        }

        return $this->formatResult($scored->all());
    }

    /**
     * @param  array<string, mixed>  $tripContext
     * @return array{
     *     context: string,
     *     sources: list<array{document_id: string, title: string, score: float}>,
     *     chunks: list<RetrievedChunk>
     * }
     */
    public function retrieveForTripContext(array $tripContext, string $query): array
    {
        $combinedQuery = trim($query);

        if ($combinedQuery === '') {
            $combinedQuery = $this->defaultQueryFromTripContext($tripContext);
        }

        return $this->retrieve($combinedQuery, $this->destinationsFromTripContext($tripContext));
    }

    /**
     * @param  list<RetrievedChunk>  $chunks
     * @return array{
     *     context: string,
     *     sources: list<array{document_id: string, title: string, score: float}>,
     *     chunks: list<RetrievedChunk>
     * }
     */
    public function formatResult(array $chunks): array
    {
        if ($chunks === []) {
            return $this->emptyResult();
        }

        $lines = ['Retrieved travel knowledge:'];
        $sources = [];

        foreach ($chunks as $chunk) {
            $lines[] = "Source: {$chunk->documentTitle}";
            $lines[] = $chunk->content;
            $lines[] = '';

            $sources[$chunk->documentId] = [
                'document_id' => $chunk->documentId,
                'title' => $chunk->documentTitle,
                'score' => round($chunk->score, 4),
            ];
        }

        return [
            'context' => trim(implode("\n", $lines)),
            'sources' => array_values($sources),
            'chunks' => $chunks,
        ];
    }

    /**
     * @param  array<string, mixed>  $tripContext
     * @return list<string>
     */
    public function destinationsFromTripContext(array $tripContext): array
    {
        $labels = [];

        foreach ([
            Arr::get($tripContext, 'destination.label'),
            Arr::get($tripContext, 'origin.label'),
        ] as $label) {
            if (is_string($label) && $label !== '') {
                $labels[] = $label;
            }
        }

        $waypoints = Arr::get($tripContext, 'waypoints', []);

        if (is_array($waypoints)) {
            foreach ($waypoints as $waypoint) {
                if (! is_array($waypoint)) {
                    continue;
                }

                $location = $waypoint['location'] ?? null;

                if (is_array($location) && is_string($location['label'] ?? null)) {
                    $labels[] = $location['label'];
                }
            }
        }

        $routePoints = Arr::get($tripContext, 'route_summary.route_points', []);

        if (is_array($routePoints)) {
            foreach ($routePoints as $point) {
                if (is_string($point) && $point !== '') {
                    $labels[] = $point;
                }
            }
        }

        return KnowledgeDocument::normalizeDestinations($labels);
    }

    /**
     * @param  array<string, mixed>  $tripContext
     */
    public function defaultQueryFromTripContext(array $tripContext): string
    {
        $parts = array_filter([
            Arr::get($tripContext, 'title'),
            Arr::get($tripContext, 'destination.label'),
            Arr::get($tripContext, 'type_label'),
            Arr::get($tripContext, 'travel_style_label'),
        ], fn ($value): bool => is_string($value) && trim($value) !== '');

        return trim(implode(' ', $parts));
    }

    /**
     * @return array{
     *     context: string,
     *     sources: list<array{document_id: string, title: string, score: float}>,
     *     chunks: list<RetrievedChunk>
     * }
     */
    private function emptyResult(): array
    {
        return [
            'context' => '',
            'sources' => [],
            'chunks' => [],
        ];
    }
}
