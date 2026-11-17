<?php

namespace App\Http\Requests\Admin;

use App\Models\CreditPack;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCreditPacksRequest extends FormRequest
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
            'packs' => ['required', 'array'],
        ];

        foreach (CreditPack::query()->pluck('id') as $packId) {
            $rules["packs.{$packId}"] = ['required', 'array'];
            $rules["packs.{$packId}.name"] = ['required', 'string', 'max:64'];
            $rules["packs.{$packId}.price"] = ['required', 'integer', 'min:1', 'max:100000'];
            $rules["packs.{$packId}.credits"] = ['required', 'integer', 'min:1', 'max:100000'];
            $rules["packs.{$packId}.is_featured"] = ['required', 'boolean'];
            $rules["packs.{$packId}.is_active"] = ['required', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'packs.*.name' => 'pack name',
            'packs.*.price' => 'price',
            'packs.*.credits' => 'credits',
        ];
    }
}
