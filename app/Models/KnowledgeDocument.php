<?php

namespace App\Models;

use App\Enums\KnowledgeDocumentStatus;
use Database\Factories\KnowledgeDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property list<string> $destinations
 * @property string $content
 * @property KnowledgeDocumentStatus $status
 * @property int $chunk_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class KnowledgeDocument extends Model
{
    /** @use HasFactory<KnowledgeDocumentFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected string $collection = 'knowledge_documents';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'destinations',
        'content',
        'status',
        'chunk_count',
    ];

    /**
     * @var list<string|array<string, int>>
     */
    protected $indexes = [
        ['slug' => 1],
        ['status' => 1],
        ['destinations' => 1],
        ['updated_at' => -1],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => KnowledgeDocumentStatus::class,
            'chunk_count' => 'integer',
        ];
    }

    /**
     * @param  Builder<KnowledgeDocument>  $query
     * @return Builder<KnowledgeDocument>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', KnowledgeDocumentStatus::Published->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function toFrontend(): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'destinations' => $this->destinationsForFrontend(),
            'content' => $this->content,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'chunk_count' => (int) ($this->chunk_count ?? 0),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function normalizeDestinations(?array $destinations): array
    {
        if (! is_array($destinations)) {
            return [];
        }

        $normalized = [];

        foreach ($destinations as $destination) {
            $value = self::normalizeDestinationToken((string) $destination);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    public static function normalizeDestinationToken(string $value): string
    {
        $value = strtolower(trim($value));

        if ($value === '') {
            return '';
        }

        $value = explode(',', $value)[0] ?? $value;

        return trim($value);
    }

    /**
     * @return list<string>
     */
    private function destinationsForFrontend(): array
    {
        $destinations = $this->getAttribute('destinations');

        return is_array($destinations)
            ? array_values(array_map(strval(...), $destinations))
            : [];
    }

    protected static function newFactory(): KnowledgeDocumentFactory
    {
        return KnowledgeDocumentFactory::new();
    }
}
