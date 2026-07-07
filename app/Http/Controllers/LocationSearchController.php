<?php

namespace App\Http\Controllers;

use App\Services\Maps\Geoapify\GeoapifyAutocomplete;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationSearchController extends Controller
{
    public function __invoke(Request $request, GeoapifyAutocomplete $autocomplete): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        if (! $autocomplete->isConfigured()) {
            return response()->json([
                'enabled' => false,
                'results' => [],
            ]);
        }

        return response()->json([
            'enabled' => true,
            'results' => $autocomplete->search($validated['q']),
        ]);
    }
}
