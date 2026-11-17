<?php

namespace App\Http\Controllers\Admin\Super;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GrantUserCreditsRequest;
use App\Models\User;
use App\Notifications\CreditsAddedNotification;
use App\Services\Credits\CreditLedger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class UserCreditController extends Controller
{
    /**
     * Add credits to a user's balance on behalf of a super admin.
     */
    public function store(GrantUserCreditsRequest $request, User $user, CreditLedger $ledger): RedirectResponse
    {
        $credits = (int) $request->validated('credits');

        $grant = $ledger->grant($user, $credits, $request->user(), $request->validated('note'));

        $user->notify(CreditsAddedNotification::forGrant($grant));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Added :credits credits to :name.', ['credits' => $credits, 'name' => $user->name]),
        ]);

        return back();
    }
}
