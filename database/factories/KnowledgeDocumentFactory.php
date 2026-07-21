<?php

namespace Database\Factories;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KnowledgeDocument>
 */
class KnowledgeDocumentFactory extends Factory
{
    protected $model = KnowledgeDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'destinations' => [KnowledgeDocument::normalizeDestinationToken(fake()->city())],
            'content' => fake()->paragraphs(3, true),
            'status' => KnowledgeDocumentStatus::Published,
            'chunk_count' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => KnowledgeDocumentStatus::Published,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => KnowledgeDocumentStatus::Draft,
        ]);
    }
}
