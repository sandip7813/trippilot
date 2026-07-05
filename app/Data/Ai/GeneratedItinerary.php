<?php

namespace App\Data\Ai;

readonly class GeneratedItinerary
{
    /**
     * @param  array<int, mixed>  $days
     * @param  array<string, mixed>  $budget
     * @param  array<int, string>  $packingList
     */
    public function __construct(
        public string $title,
        public array $days,
        public array $budget = [],
        public array $packingList = [],
        public string $summary = '',
    ) {}
}
