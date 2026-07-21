<?php

namespace Database\Factories;

use App\Models\KnowledgeChunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeChunk>
 */
class KnowledgeChunkFactory extends Factory
{
    protected $model = KnowledgeChunk::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => (string) fake()->uuid(),
            'document_title' => fake()->sentence(3),
            'chunk_index' => 0,
            'content' => fake()->paragraph(),
            'embedding' => [1.0, 0.0, 0.0],
            'destinations' => ['goa'],
        ];
    }
}
