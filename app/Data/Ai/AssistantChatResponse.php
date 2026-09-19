<?php

namespace App\Data\Ai;

readonly class AssistantChatResponse
{
    public function __construct(
        public string $message,
    ) {}
}
