<?php

namespace App\Contracts\Ai;

interface EmbeddingService
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array;
}
