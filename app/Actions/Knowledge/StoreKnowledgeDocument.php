<?php

namespace App\Actions\Knowledge;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeIndexer;
use Illuminate\Support\Str;

class StoreKnowledgeDocument
{
    public function __construct(private KnowledgeIndexer $indexer) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function __invoke(array $validated): KnowledgeDocument
    {
        $document = KnowledgeDocument::query()->create([
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug((string) $validated['title']),
            'destinations' => KnowledgeDocument::normalizeDestinations(
                $this->parseDestinations($validated['destinations'] ?? ''),
            ),
            'content' => $validated['content'],
            'status' => KnowledgeDocumentStatus::from($validated['status']),
            'chunk_count' => 0,
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

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (KnowledgeDocument::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
