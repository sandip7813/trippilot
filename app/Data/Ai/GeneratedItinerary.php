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

    /**
     * @return array{
     *     days: array<int, mixed>,
     *     summary: string,
     *     packing_list: array<int, string>,
     *     budget_breakdown: array<string, mixed>
     * }
     */
    public function toTripItinerary(): array
    {
        return [
            'days' => $this->days,
            'summary' => $this->summary,
            'packing_list' => $this->packingList,
            'budget_breakdown' => $this->budget,
        ];
    }
}
