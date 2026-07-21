<?php

namespace App\Actions\Admin;

use App\Services\Admin\PlatformSettings;
use Illuminate\Support\Facades\Artisan;

class UpdatePlatformIntegrations
{
    public function __construct(private PlatformSettings $platformSettings) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function __invoke(array $validated): void
    {
        $this->platformSettings->set('maps.driver', $validated['maps_driver']);
        $this->platformSettings->set('weather.driver', $validated['weather_driver']);
        $this->platformSettings->set('ai.driver', $validated['ai_driver']);
        $this->platformSettings->set('trains.driver', $validated['trains_driver']);
        $this->platformSettings->set('trip_covers.driver', $validated['trip_covers_driver']);
        $this->platformSettings->set('trip_covers.enabled', (bool) $validated['trip_covers_enabled']);
        $this->platformSettings->set('trip_covers.use_gemini_prompt', (bool) $validated['trip_covers_use_gemini_prompt']);
        $this->platformSettings->set('trip_covers.pollinations_fallback', (bool) $validated['trip_covers_pollinations_fallback']);
        $this->platformSettings->set('ai.gemini.image_enabled', (bool) $validated['gemini_image_enabled']);
        $this->platformSettings->set('recaptcha.enabled', (bool) $validated['recaptcha_enabled']);
        $this->platformSettings->set('brand.logo', $validated['logo']);

        $this->updateSecret('maps.geoapify.api_key', $validated['geoapify_api_key'] ?? null);
        $this->updateSecret('weather.openweathermap.api_key', $validated['openweathermap_api_key'] ?? null);
        $this->updateSecret('ai.gemini.api_key', $validated['gemini_api_key'] ?? null);
        $this->updateSecret('trains.railradar.api_key', $validated['railradar_api_key'] ?? null);
        $this->updateSecret('trip_covers.unsplash.access_key', $validated['unsplash_access_key'] ?? null);

        PlatformSettings::applyToConfig();

        Artisan::call('config:clear');
    }

    private function updateSecret(string $storageKey, mixed $value): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $this->platformSettings->set($storageKey, trim($value));
    }
}
