<?php

namespace App\Services\TripCovers;

class TripCoverPromptBuilder
{
    public function __construct(
        private GeminiTripCoverPromptEnhancer $enhancer,
        private TripCoverPlacePhrase $placePhrase,
    ) {}

    /**
     * @param  array<string, mixed>|null  $destination
     */
    public function build(?array $destination, ?string $travelStyle): string
    {
        $enhanced = $this->enhancer->enhance($destination, $travelStyle);

        if ($enhanced !== null) {
            return $enhanced;
        }

        return $this->fallbackPrompt($destination, $travelStyle);
    }

    /**
     * @param  array<string, mixed>|null  $destination
     */
    public function specificPlacePhrase(?array $destination): string
    {
        return $this->placePhrase->resolve($destination);
    }

    /**
     * @param  array<string, mixed>|null  $destination
     */
    public function seed(?array $destination): int
    {
        $key = strtolower(trim((string) ($destination['label'] ?? 'destination')))
            .'|'.strtolower((string) ($destination['country_code'] ?? ''));

        return abs(crc32($key)) % 999_999 + 1;
    }

    /**
     * @param  array<string, mixed>|null  $destination
     */
    private function fallbackPrompt(?array $destination, ?string $travelStyle): string
    {
        $place = $this->placePhrase->resolve($destination);
        $styleHint = $travelStyle !== null && $travelStyle !== ''
            ? " {$travelStyle} travel atmosphere."
            : '';

        return "Photorealistic wide travel banner set in {$place}. "
            ."Show the authentic local environment of {$place}: regional coastline, streets, temples, or landscape as they appear in that exact city. "
            .'Golden hour documentary travel photography, vibrant natural colors, wide composition.'
            ."{$styleHint} No text, no logos, no watermark, no close-up faces.";
    }
}
