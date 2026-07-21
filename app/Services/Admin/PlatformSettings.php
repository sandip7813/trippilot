<?php

namespace App\Services\Admin;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PlatformSettings
{
    private const CACHE_KEY = 'platform_settings:all';

    /**
     * @var array<string, array{config: string, type: 'string'|'boolean'|'secret'}>
     */
    public const DEFINITIONS = [
        'maps.driver' => [
            'config' => 'integrations.maps.driver',
            'type' => 'string',
        ],
        'maps.geoapify.api_key' => [
            'config' => 'integrations.maps.drivers.geoapify.api_key',
            'type' => 'secret',
        ],
        'weather.driver' => [
            'config' => 'integrations.weather.driver',
            'type' => 'string',
        ],
        'weather.openweathermap.api_key' => [
            'config' => 'integrations.weather.drivers.openweathermap.api_key',
            'type' => 'secret',
        ],
        'ai.driver' => [
            'config' => 'integrations.ai.driver',
            'type' => 'string',
        ],
        'ai.gemini.api_key' => [
            'config' => 'integrations.ai.drivers.gemini.api_key',
            'type' => 'secret',
        ],
        'ai.gemini.image_enabled' => [
            'config' => 'integrations.ai.drivers.gemini.image_enabled',
            'type' => 'boolean',
        ],
        'trains.driver' => [
            'config' => 'integrations.trains.driver',
            'type' => 'string',
        ],
        'trains.railradar.api_key' => [
            'config' => 'integrations.trains.drivers.railradar.api_key',
            'type' => 'secret',
        ],
        'trip_covers.driver' => [
            'config' => 'integrations.trip_covers.driver',
            'type' => 'string',
        ],
        'trip_covers.enabled' => [
            'config' => 'integrations.trip_covers.enabled',
            'type' => 'boolean',
        ],
        'trip_covers.use_gemini_prompt' => [
            'config' => 'integrations.trip_covers.use_gemini_prompt',
            'type' => 'boolean',
        ],
        'trip_covers.pollinations_fallback' => [
            'config' => 'integrations.trip_covers.pollinations_fallback',
            'type' => 'boolean',
        ],
        'trip_covers.unsplash.access_key' => [
            'config' => 'integrations.trip_covers.drivers.unsplash.access_key',
            'type' => 'secret',
        ],
        'recaptcha.enabled' => [
            'config' => 'recaptcha.enabled',
            'type' => 'boolean',
        ],
        'brand.logo' => [
            'config' => 'trippilot.logo',
            'type' => 'string',
        ],
    ];

    public static function applyToConfig(): void
    {
        try {
            $settings = self::storedValues();
        } catch (Throwable) {
            return;
        }

        foreach (self::DEFINITIONS as $storageKey => $definition) {
            if (! array_key_exists($storageKey, $settings)) {
                continue;
            }

            config([$definition['config'] => self::castFromStorage(
                $settings[$storageKey],
                $definition['type'],
            )]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function formValues(): array
    {
        return [
            'maps_driver' => (string) config('integrations.maps.driver', 'geoapify'),
            'weather_driver' => (string) config('integrations.weather.driver', 'open_meteo'),
            'ai_driver' => (string) config('integrations.ai.driver', 'gemini'),
            'trains_driver' => (string) config('integrations.trains.driver', 'railradar'),
            'trip_covers_driver' => (string) config('integrations.trip_covers.driver', 'rotating'),
            'trip_covers_enabled' => (bool) config('integrations.trip_covers.enabled', true),
            'trip_covers_use_gemini_prompt' => (bool) config('integrations.trip_covers.use_gemini_prompt', true),
            'trip_covers_pollinations_fallback' => (bool) config('integrations.trip_covers.pollinations_fallback', true),
            'gemini_image_enabled' => (bool) config('integrations.ai.drivers.gemini.image_enabled', true),
            'geoapify_api_key_configured' => filled(config('integrations.maps.drivers.geoapify.api_key')),
            'openweathermap_api_key_configured' => filled(config('integrations.weather.drivers.openweathermap.api_key')),
            'gemini_api_key_configured' => filled(config('integrations.ai.drivers.gemini.api_key')),
            'railradar_api_key_configured' => filled(config('integrations.trains.drivers.railradar.api_key')),
            'unsplash_access_key_configured' => filled(config('integrations.trip_covers.drivers.unsplash.access_key')),
            'recaptcha_enabled' => (bool) config('recaptcha.enabled', true),
            'recaptcha_configured' => filled(config('recaptcha.site_key')) && filled(config('recaptcha.secret_key')),
            'logo' => (string) config('trippilot.logo', 'compass'),
        ];
    }

    /**
     * @return array<string, array{label: string, description: string, configured: bool, requires_key: bool}>
     */
    public function integrationStatuses(): array
    {
        return [
            'maps' => [
                'label' => 'Geoapify',
                'description' => 'Maps, geocoding, routing, and places search',
                'configured' => filled(config('integrations.maps.drivers.geoapify.api_key')),
                'requires_key' => true,
            ],
            'weather' => [
                'label' => 'Weather',
                'description' => config('integrations.weather.driver') === 'openweathermap'
                    ? 'OpenWeatherMap forecasts'
                    : 'Open-Meteo forecasts (no API key required)',
                'configured' => config('integrations.weather.driver') === 'openweathermap'
                    ? filled(config('integrations.weather.drivers.openweathermap.api_key'))
                    : true,
                'requires_key' => config('integrations.weather.driver') === 'openweathermap',
            ],
            'ai' => [
                'label' => 'Google Gemini',
                'description' => 'Itinerary generation, chat, embeddings, and optional cover images',
                'configured' => filled(config('integrations.ai.drivers.gemini.api_key')),
                'requires_key' => true,
            ],
            'trains' => [
                'label' => 'RailRadar',
                'description' => 'Indian Railways timings and station lookup',
                'configured' => filled(config('integrations.trains.drivers.railradar.api_key')),
                'requires_key' => true,
            ],
            'trip_covers' => [
                'label' => 'Trip covers',
                'description' => 'Destination cover image generation ladder',
                'configured' => ! config('integrations.trip_covers.enabled', true)
                    || config('integrations.trip_covers.driver') !== 'unsplash'
                    || filled(config('integrations.trip_covers.drivers.unsplash.access_key')),
                'requires_key' => config('integrations.trip_covers.driver') === 'unsplash',
            ],
            'recaptcha' => [
                'label' => 'Google reCAPTCHA v3',
                'description' => config('recaptcha.enabled', true)
                    ? 'Signup bot protection (enabled)'
                    : 'Signup bot protection (disabled)',
                'configured' => filled(config('recaptcha.site_key')) && filled(config('recaptcha.secret_key')),
                'requires_key' => true,
            ],
        ];
    }

    public function set(string $storageKey, mixed $value): void
    {
        if (! isset(self::DEFINITIONS[$storageKey])) {
            return;
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => $storageKey],
            ['value' => self::castToStorage($value, self::DEFINITIONS[$storageKey]['type'])],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, string|null>
     */
    private static function storedValues(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return PlatformSetting::query()
                ->get()
                ->mapWithKeys(fn (PlatformSetting $setting): array => [
                    $setting->key => $setting->value,
                ])
                ->all();
        });
    }

    private static function castFromStorage(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }

    private static function castToStorage(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }
}
