<?php

namespace App\Models;

use Database\Factories\KnowledgeChunkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $id
 * @property string $document_id
 * @property string $document_title
 * @property int $chunk_index
 * @property string $content
 * @property list<float> $embedding
 * @property list<string> $destinations
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class KnowledgeChunk extends Model
{
    /** @use HasFactory<KnowledgeChunkFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected string $collection = 'knowledge_chunks';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'document_title',
        'chunk_index',
        'content',
        'embedding',
        'destinations',
    ];

    /**
     * @var list<string|array<string, int>>
     */
    protected $indexes = [
        ['document_id' => 1],
        ['destinations' => 1],
        ['chunk_index' => 1],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
        ];
    }

    protected static function newFactory(): KnowledgeChunkFactory
    {
        return KnowledgeChunkFactory::new();
    }
}
