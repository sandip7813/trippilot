<?php

namespace App\Http\Controllers\Admin\Super;

use App\Actions\Admin\UpdatePlatformIntegrations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateIntegrationSettingsRequest;
use App\Services\Admin\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __invoke(PlatformSettings $platformSettings): Response
    {
        return Inertia::render('admin/super/Settings', [
            'integrations' => $platformSettings->formValues(),
            'integration_statuses' => $platformSettings->integrationStatuses(),
            'driver_options' => $this->driverOptions(),
        ]);
    }

    public function updateIntegrations(
        UpdateIntegrationSettingsRequest $request,
        UpdatePlatformIntegrations $updatePlatformIntegrations,
    ): RedirectResponse {
        $updatePlatformIntegrations($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Settings saved.'),
        ]);

        return back();
    }

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function driverOptions(): array
    {
        return [
            'maps' => [
                ['value' => 'geoapify', 'label' => 'Geoapify'],
            ],
            'weather' => [
                ['value' => 'open_meteo', 'label' => 'Open-Meteo (free)'],
                ['value' => 'openweathermap', 'label' => 'OpenWeatherMap'],
            ],
            'ai' => [
                ['value' => 'gemini', 'label' => 'Google Gemini'],
            ],
            'trains' => [
                ['value' => 'railradar', 'label' => 'RailRadar'],
            ],
            'trip_covers' => [
                ['value' => 'rotating', 'label' => 'Rotating ladder (recommended)'],
                ['value' => 'unsplash', 'label' => 'Unsplash only'],
                ['value' => 'pollinations', 'label' => 'Pollinations only'],
                ['value' => 'gemini', 'label' => 'Gemini image only'],
                ['value' => 'none', 'label' => 'Disabled'],
            ],
        ];
    }
}
