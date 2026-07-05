<?php

namespace App\Contracts\Ai;

use App\Data\Ai\ChatResponse;
use App\Data\Ai\GeneratedItinerary;

interface TripGenerator
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function generate(string $prompt, array $context = []): GeneratedItinerary;
}

interface ChatAssistant
{
    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $tripContext
     */
    public function chat(string $message, array $history, array $tripContext = []): ChatResponse;
}

interface EmbeddingService
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array;
}
