<?php

namespace App\Http\Controllers\Admin\Super;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCreditPacksRequest;
use App\Models\CreditPack;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CreditPackController extends Controller
{
    /**
     * Update the price, credits, and visibility of every credit pack. Orders
     * already placed keep the price and credits they were created with.
     */
    public function update(UpdateCreditPacksRequest $request): RedirectResponse
    {
        /** @var array<int|string, array{name: string, price: int|string, credits: int|string, is_featured: bool|string, is_active: bool|string}> $packs */
        $packs = $request->validated('packs');

        foreach (CreditPack::query()->whereIn('id', array_keys($packs))->get() as $pack) {
            $values = $packs[$pack->id];

            $pack->update([
                'name' => $values['name'],
                'price_paise' => (int) $values['price'] * 100,
                'credits' => (int) $values['credits'],
                'is_featured' => filter_var($values['is_featured'], FILTER_VALIDATE_BOOLEAN),
                'is_active' => filter_var($values['is_active'], FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Credit packs saved.'),
        ]);

        return back();
    }
}
