<?php

namespace App\Models;

use Database\Factories\AssistantConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $id
 * @property int $user_id
 * @property string $title
 * @property list<array<string, mixed>>|null $messages
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AssistantConversation extends Model
{
    /** @use HasFactory<AssistantConversationFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected string $collection = 'assistant_conversations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'messages',
    ];

    /**
     * @var list<string|array<string, int>>
     */
    protected $indexes = [
        ['user_id' => 1],
        ['updated_at' => -1],
    ];

    /**
     * @return list<array{id: string, role: string, content: string, created_at: string, rag_sources?: list<array{document_id: string, title: string, score?: float|null}>}>
     */
    public static function normalizeMessages(mixed $messages): array
    {
        if (! is_array($messages)) {
            return [];
        }

        $normalized = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $role = (string) ($message['role'] ?? '');
            $content = trim((string) ($message['content'] ?? ''));

            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }

            $entry = [
                'id' => (string) ($message['id'] ?? ''),
                'role' => $role,
                'content' => $content,
                'created_at' => (string) ($message['created_at'] ?? now()->toIso8601String()),
            ];

            if ($role === 'assistant' && is_array($message['rag_sources'] ?? null)) {
                $entry['rag_sources'] = array_values(array_filter(array_map(
                    function (mixed $source): ?array {
                        if (! is_array($source)) {
                            return null;
                        }

                        $documentId = (string) ($source['document_id'] ?? '');
                        $title = (string) ($source['title'] ?? '');

                        if ($documentId === '' || $title === '') {
                            return null;
                        }

                        return [
                            'document_id' => $documentId,
                            'title' => $title,
                            'score' => isset($source['score']) ? (float) $source['score'] : null,
                        ];
                    },
                    $message['rag_sources'],
                )));
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function toFrontendArray(): array
    {
        return [
            'id' => (string) $this->id,
            'title' => (string) $this->title,
            'messages' => self::normalizeMessages($this->messages),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected static function newFactory(): AssistantConversationFactory
    {
        return AssistantConversationFactory::new();
    }
}
