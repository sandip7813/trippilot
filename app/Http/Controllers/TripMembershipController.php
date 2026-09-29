<?php

namespace App\Http\Controllers;

use App\Actions\Trips\LeaveOpenTrip;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use RuntimeException;

class TripMembershipController extends Controller
{
    public function leave(Trip $trip, LeaveOpenTrip $leaveOpenTrip): RedirectResponse
    {
        try {
            $leaveOpenTrip($trip, request()->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['membership' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left this trip.')]);

        return redirect()->route('trips.index');
    }
}
