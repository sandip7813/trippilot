<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntegrationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'maps_driver' => ['required', Rule::in(['geoapify'])],
            'weather_driver' => ['required', Rule::in(['open_meteo', 'openweathermap'])],
            'ai_driver' => ['required', Rule::in(['gemini'])],
            'trains_driver' => ['required', Rule::in(['railradar'])],
            'trip_covers_driver' => ['required', Rule::in(['rotating', 'unsplash', 'pollinations', 'gemini', 'none'])],
            'trip_covers_enabled' => ['required', 'boolean'],
            'trip_covers_use_gemini_prompt' => ['required', 'boolean'],
            'trip_covers_pollinations_fallback' => ['required', 'boolean'],
            'gemini_image_enabled' => ['required', 'boolean'],
            'recaptcha_enabled' => ['required', 'boolean'],
            'logo' => ['required', Rule::in(['plane', 'compass', 'pin', 'globe', 'monogram'])],
            'geoapify_api_key' => ['nullable', 'string', 'max:255'],
            'openweathermap_api_key' => ['nullable', 'string', 'max:255'],
            'gemini_api_key' => ['nullable', 'string', 'max:255'],
            'railradar_api_key' => ['nullable', 'string', 'max:255'],
            'unsplash_access_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'trip_covers_enabled' => $this->boolean('trip_covers_enabled'),
            'trip_covers_use_gemini_prompt' => $this->boolean('trip_covers_use_gemini_prompt'),
            'trip_covers_pollinations_fallback' => $this->boolean('trip_covers_pollinations_fallback'),
            'gemini_image_enabled' => $this->boolean('gemini_image_enabled'),
            'recaptcha_enabled' => $this->boolean('recaptcha_enabled'),
        ]);
    }
}
