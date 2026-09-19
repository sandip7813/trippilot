<?php

namespace App\Contracts\Ai;

use App\Data\Ai\AssistantChatResponse;

interface TravelAssistant
{
    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $context
     */
    public function chat(string $message, array $history, array $context = []): AssistantChatResponse;
}
