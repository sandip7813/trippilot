<?php

namespace App\Http\Controllers;

use App\Actions\Trips\SubmitTripReport;
use App\Http\Requests\SubmitTripReportRequest;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TripReportController extends Controller
{
    public function store(SubmitTripReportRequest $request, Trip $trip, SubmitTripReport $submitTripReport): RedirectResponse
    {
        $submitTripReport($trip, $request->user(), $request->validated('reason'), $request->validated('message'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thanks — our team will review this trip.')]);

        return back();
    }
}
