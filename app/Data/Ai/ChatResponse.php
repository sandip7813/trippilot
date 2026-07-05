<?php

namespace App\Data\Ai;

readonly class ChatResponse
{
    /**
     * @param  array<string, mixed>|null  $patch
     */
    public function __construct(
        public string $message,
        public ?array $patch = null,
    ) {}
}
