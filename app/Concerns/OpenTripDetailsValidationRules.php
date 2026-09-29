<?php

namespace App\Concerns;

use App\Enums\OpenTripCostModel;
use Illuminate\Validation\Rule;

trait OpenTripDetailsValidationRules
{
    /**
     * @param  string  $prefix  e.g. "open_trip." to validate a nested
     *                          `open_trip` array on the create forms;
     *                          left empty for the flat update endpoint.
     * @return array<string, mixed>
     */
    protected function openTripDetailsRules(string $prefix = ''): array
    {
        return [
            "{$prefix}category" => ['nullable', 'string', 'max:60'],
            "{$prefix}max_group_size" => ['nullable', 'integer', 'min:1', 'max:200'],
            "{$prefix}join_deadline" => ['nullable', 'date'],
            "{$prefix}difficulty" => ['nullable', 'string', 'max:60'],
            "{$prefix}requirements" => ['nullable', 'string', 'max:2000'],
            "{$prefix}meeting_point" => ['nullable', 'string', 'max:500'],
            "{$prefix}rules" => ['nullable', 'string', 'max:2000'],
            "{$prefix}cost_model" => ['nullable', Rule::enum(OpenTripCostModel::class)],
            "{$prefix}cost_amount" => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            "{$prefix}cost_currency" => ['nullable', 'string', 'size:3'],
            "{$prefix}cost_inclusions" => ['nullable', 'string', 'max:2000'],
            "{$prefix}share_itinerary_with_members" => ['sometimes', 'boolean'],
            "{$prefix}member_names_visible" => ['sometimes', 'boolean'],
        ];
    }
}
