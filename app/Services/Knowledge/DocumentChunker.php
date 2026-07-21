<?php

namespace App\Services\Knowledge;

class DocumentChunker
{
    public function __construct(
        private int $maxCharacters = 1800,
        private int $overlapCharacters = 200,
    ) {
        $this->maxCharacters = (int) config('trippilot.rag.chunk_max_characters', 1800);
        $this->overlapCharacters = (int) config('trippilot.rag.chunk_overlap_characters', 200);
    }

    /**
     * @return list<string>
     */
    public function chunk(string $content): array
    {
        $normalized = trim(preg_replace("/\r\n?|\n/", "\n", $content) ?? '');

        if ($normalized === '') {
            return [];
        }

        $paragraphs = preg_split("/\n{2,}/", $normalized) ?: [$normalized];
        $paragraphs = array_values(array_filter(array_map(trim(...), $paragraphs)));

        if ($paragraphs === []) {
            return [];
        }

        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            if ($paragraph === '') {
                continue;
            }

            $candidate = $current === '' ? $paragraph : $current."\n\n".$paragraph;

            if (strlen($candidate) <= $this->maxCharacters) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $chunks = [...$chunks, ...$this->splitOversized($current)];
            }

            $current = $paragraph;
        }

        if ($current !== '') {
            $chunks = [...$chunks, ...$this->splitOversized($current)];
        }

        return $this->applyOverlap($chunks);
    }

    /**
     * @return list<string>
     */
    private function splitOversized(string $text): array
    {
        if (strlen($text) <= $this->maxCharacters) {
            return [$text];
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $text) ?: [$text];
        $chunks = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);

            if ($sentence === '') {
                continue;
            }

            $candidate = $current === '' ? $sentence : $current.' '.$sentence;

            if (strlen($candidate) <= $this->maxCharacters) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $chunks[] = $current;
            }

            if (strlen($sentence) > $this->maxCharacters) {
                $chunks = [...$chunks, ...str_split($sentence, $this->maxCharacters)];

                continue;
            }

            $current = $sentence;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * @param  list<string>  $chunks
     * @return list<string>
     */
    private function applyOverlap(array $chunks): array
    {
        if ($this->overlapCharacters <= 0 || count($chunks) <= 1) {
            return $chunks;
        }

        $merged = [$chunks[0]];

        for ($index = 1; $index < count($chunks); $index++) {
            $previous = $merged[$index - 1];
            $overlap = substr($previous, max(0, strlen($previous) - $this->overlapCharacters));
            $merged[] = trim($overlap.' '.$chunks[$index]);
        }

        return array_values(array_filter(array_map(trim(...), $merged)));
    }
}
