<?php

namespace App\Contracts\Ai;

use App\Data\Ai\ChatResponse;

interface ChatAssistant
{
    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $tripContext
     */
    public function chat(string $message, array $history, array $tripContext = []): ChatResponse;
}
