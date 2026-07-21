<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Admin\PlatformSettings;

test('super admin can view and update integration settings', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.super.settings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/super/Settings')
            ->has('integrations')
            ->has('integration_statuses')
            ->has('driver_options'));

    $this->actingAs($superAdmin)
        ->from(route('admin.super.settings'))
        ->patch(route('admin.super.settings.integrations'), [
            'maps_driver' => 'geoapify',
            'weather_driver' => 'open_meteo',
            'ai_driver' => 'gemini',
            'trains_driver' => 'railradar',
            'trip_covers_driver' => 'rotating',
            'trip_covers_enabled' => true,
            'trip_covers_use_gemini_prompt' => false,
            'trip_covers_pollinations_fallback' => true,
            'gemini_image_enabled' => false,
            'recaptcha_enabled' => false,
            'logo' => 'pin',
            'gemini_api_key' => 'stored-gemini-key',
        ])
        ->assertRedirect(route('admin.super.settings'));

    expect(PlatformSetting::query()->where('key', 'ai.gemini.api_key')->exists())->toBeTrue()
        ->and(PlatformSetting::query()->where('key', 'trip_covers.use_gemini_prompt')->first()?->value)
        ->toBe('false')
        ->and(PlatformSetting::query()->where('key', 'recaptcha.enabled')->first()?->value)
        ->toBe('false')
        ->and(PlatformSetting::query()->where('key', 'brand.logo')->first()?->value)
        ->toBe('pin');

    PlatformSettings::applyToConfig();

    expect(config('integrations.ai.drivers.gemini.api_key'))->toBe('stored-gemini-key')
        ->and(config('integrations.trip_covers.use_gemini_prompt'))->toBeFalse()
        ->and(config('integrations.ai.drivers.gemini.image_enabled'))->toBeFalse()
        ->and(config('recaptcha.enabled'))->toBeFalse()
        ->and(config('trippilot.logo'))->toBe('pin');
});

test('super admin can change brand logo from settings', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->from(route('admin.super.settings'))
        ->patch(route('admin.super.settings.integrations'), [
            'maps_driver' => 'geoapify',
            'weather_driver' => 'open_meteo',
            'ai_driver' => 'gemini',
            'trains_driver' => 'railradar',
            'trip_covers_driver' => 'rotating',
            'trip_covers_enabled' => true,
            'trip_covers_use_gemini_prompt' => true,
            'trip_covers_pollinations_fallback' => true,
            'gemini_image_enabled' => true,
            'recaptcha_enabled' => true,
            'logo' => 'globe',
        ])
        ->assertRedirect(route('admin.super.settings'));

    PlatformSettings::applyToConfig();

    expect(config('trippilot.logo'))->toBe('globe');

    $this->actingAs($superAdmin)
        ->get(route('admin.super.settings'))
        ->assertInertia(fn ($page) => $page
            ->where('brand.logo', 'globe')
            ->where('integrations.logo', 'globe'));
});

test('super admin can enable recaptcha from settings', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    config(['recaptcha.enabled' => false]);

    $this->actingAs($superAdmin)
        ->from(route('admin.super.settings'))
        ->patch(route('admin.super.settings.integrations'), [
            'maps_driver' => 'geoapify',
            'weather_driver' => 'open_meteo',
            'ai_driver' => 'gemini',
            'trains_driver' => 'railradar',
            'trip_covers_driver' => 'rotating',
            'trip_covers_enabled' => true,
            'trip_covers_use_gemini_prompt' => true,
            'trip_covers_pollinations_fallback' => true,
            'gemini_image_enabled' => true,
            'recaptcha_enabled' => true,
            'logo' => 'compass',
        ])
        ->assertRedirect(route('admin.super.settings'));

    PlatformSettings::applyToConfig();

    expect(config('recaptcha.enabled'))->toBeTrue();
});

test('super admin cannot save an invalid logo', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->from(route('admin.super.settings'))
        ->patch(route('admin.super.settings.integrations'), [
            'maps_driver' => 'geoapify',
            'weather_driver' => 'open_meteo',
            'ai_driver' => 'gemini',
            'trains_driver' => 'railradar',
            'trip_covers_driver' => 'rotating',
            'trip_covers_enabled' => true,
            'trip_covers_use_gemini_prompt' => true,
            'trip_covers_pollinations_fallback' => true,
            'gemini_image_enabled' => true,
            'recaptcha_enabled' => true,
            'logo' => 'invalid-logo',
        ])
        ->assertSessionHasErrors('logo');
});

test('admins cannot update integration settings', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patch(route('admin.super.settings.integrations'), [
            'maps_driver' => 'geoapify',
            'weather_driver' => 'open_meteo',
            'ai_driver' => 'gemini',
            'trains_driver' => 'railradar',
            'trip_covers_driver' => 'rotating',
            'trip_covers_enabled' => true,
            'trip_covers_use_gemini_prompt' => true,
            'trip_covers_pollinations_fallback' => true,
            'gemini_image_enabled' => true,
            'recaptcha_enabled' => true,
            'logo' => 'compass',
        ])
        ->assertForbidden();
});

test('regular users cannot access super admin settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.super.settings'))
        ->assertForbidden();
});
