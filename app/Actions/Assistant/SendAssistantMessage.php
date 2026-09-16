<?php

namespace App\Actions\Assistant;

use App\Contracts\Ai\TravelAssistant;
use App\Exceptions\AiGenerationException;
use App\Models\AssistantConversation;
use App\Models\User;
use App\Services\Ai\GeminiUsageLimiter;
use App\Services\Assistant\AssistantContextBuilder;
use Illuminate\Support\Str;

class SendAssistantMessage
{
    public function __construct(
        private TravelAssistant $travelAssistant,
        private AssistantContextBuilder $contextBuilder,
        private GeminiUsageLimiter $usageLimiter,
    ) {}

    /**
     * @return array{conversation: AssistantConversation}
     */
    public function __invoke(AssistantConversation $conversation, User $user, string $message): array
    {
        if (! $this->usageLimiter->hasRemaining($user)) {
            throw new AiGenerationException(__(
                "You've reached today's AI usage limit (:limit requests). Please try again tomorrow.",
                ['limit' => $this->usageLimiter->dailyLimit()],
            ));
        }

        $this->usageLimiter->increment($user);

        $history = AssistantConversation::normalizeMessages($conversation->messages);
        $context = $this->contextBuilder->build($user, $message);

        $response = $this->travelAssistant->chat($message, $history, $context);

        $ragSources = is_array($context['rag_sources'] ?? null)
            ? array_values(array_map(
                fn (array $source): array => [
                    'document_id' => (string) ($source['document_id'] ?? ''),
                    'title' => (string) ($source['title'] ?? ''),
                    'score' => isset($source['score']) ? (float) $source['score'] : null,
                ],
                $context['rag_sources'],
            ))
            : [];

        $ragSources = array_values(array_filter(
            $ragSources,
            fn (array $source): bool => $source['document_id'] !== '' && $source['title'] !== '',
        ));

        $userMessage = [
            'id' => (string) Str::uuid(),
            'role' => 'user',
            'content' => trim($message),
            'created_at' => now()->toIso8601String(),
        ];

        $assistantMessage = [
            'id' => (string) Str::uuid(),
            'role' => 'assistant',
            'content' => $response->message,
            'created_at' => now()->toIso8601String(),
        ];

        if ($ragSources !== []) {
            $assistantMessage['rag_sources'] = $ragSources;
        }

        $messages = [...$history, $userMessage, $assistantMessage];

        $updates = [
            'messages' => $messages,
        ];

        if ($conversation->title === 'New conversation' && count($history) === 0) {
            $updates['title'] = AssistantContextBuilder::titleFromMessage($message);
        }

        $conversation->update($updates);

        return [
            'conversation' => $conversation->fresh(),
        ];
    }
}
