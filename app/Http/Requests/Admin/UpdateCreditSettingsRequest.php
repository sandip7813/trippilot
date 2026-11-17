<?php

namespace App\Http\Requests\Admin;

use App\Enums\CreditService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCreditSettingsRequest extends FormRequest
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
        $rules = [
            'enabled' => ['required', 'boolean'],
            'costs' => ['required', 'array'],
        ];

        foreach (CreditService::cases() as $service) {
            $rules["costs.{$service->value}"] = ['required', 'integer', 'min:0', 'max:1000'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (CreditService::cases() as $service) {
            $attributes["costs.{$service->value}"] = $service->label();
        }

        return $attributes;
    }
}
