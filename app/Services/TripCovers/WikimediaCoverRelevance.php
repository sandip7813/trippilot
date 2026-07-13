<?php

namespace App\Services\TripCovers;

class WikimediaCoverRelevance
{
    public function __construct(private TripCoverPlacePhrase $placePhrase) {}

    /**
     * @param  array<string, mixed>  $destination
     */
    public function matchesDestination(array $destination, string ...$textParts): bool
    {
        $text = strtolower(trim(implode(' ', array_filter($textParts))));

        if ($text === '') {
            return false;
        }

        foreach ($this->conflictingTerms($destination) as $term) {
            if (str_contains($text, $term)) {
                return false;
            }
        }

        $terms = $this->significantTerms($destination);

        if ($terms === []) {
            return true;
        }

        foreach ($terms as $term) {
            if (str_contains($text, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $destination
     * @return list<string>
     */
    private function significantTerms(array $destination): array
    {
        $generic = [
            'india',
            'west',
            'bengal',
            'station',
            'landscape',
            'railway',
            'travel',
            'tourism',
            'city',
            'town',
            'village',
            'district',
            'state',
            'province',
            'region',
            'country',
            'university',
            'campus',
        ];

        $terms = [];

        foreach ($this->placePhrase->searchPhrases($destination) as $phrase) {
            foreach (preg_split('/[\s,]+/', strtolower($phrase)) ?: [] as $term) {
                $term = trim($term);

                if (strlen($term) < 4 || in_array($term, $generic, true)) {
                    continue;
                }

                $terms[] = $term;
            }
        }

        foreach ($this->placePhrase->curatedQueries($destination) as $query) {
            foreach (preg_split('/[\s,]+/', strtolower($query)) ?: [] as $term) {
                $term = trim($term);

                if (strlen($term) < 4 || in_array($term, $generic, true)) {
                    continue;
                }

                $terms[] = $term;
            }
        }

        return array_values(array_unique($terms));
    }

    /**
     * @param  array<string, mixed>  $destination
     * @return list<string>
     */
    private function conflictingTerms(array $destination): array
    {
        $label = strtolower(trim((string) ($destination['label'] ?? '')));

        $terms = [];

        if (! str_contains($label, 'noida') && ! str_contains($label, 'delhi ncr')) {
            $terms = array_merge($terms, [
                'shiv nadar',
                'greater noida',
                'noida',
            ]);
        }

        if (str_contains($label, 'shantiniketan') || str_contains($label, 'bolpur')) {
            $terms = array_merge($terms, [
                'dhaka',
                'bangladesh',
            ]);
        }

        return array_values(array_unique($terms));
    }
}
