<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardStats;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(AdminDashboardStats $stats): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => $stats->snapshot(),
        ]);
    }
}
