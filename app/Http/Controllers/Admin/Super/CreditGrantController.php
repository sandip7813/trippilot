<?php

namespace App\Http\Controllers\Admin\Super;

use App\Enums\CreditTransactionType;
use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use Inertia\Inertia;
use Inertia\Response;

class CreditGrantController extends Controller
{
    /**
     * List every time a super admin added credits to a user, newest first.
     */
    public function index(): Response
    {
        $grants = CreditTransaction::query()
            ->with(['user', 'grantedBy'])
            ->where('type', CreditTransactionType::AdminGrant)
            ->latest()
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/super/CreditGrants', [
            'grants' => $grants->through(fn (CreditTransaction $grant): array => [
                'id' => $grant->id,
                'credits' => $grant->amount,
                'balance_after' => $grant->balance_after,
                'note' => $grant->note,
                'user_name' => $grant->user?->name,
                'user_email' => $grant->user?->email,
                'granted_by_name' => $grant->grantedBy?->name,
                'created_at' => $grant->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
