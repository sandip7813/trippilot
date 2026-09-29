<?php

namespace App\Http\Controllers;

use App\Actions\Trips\PublishOpenTrip;
use App\Http\Requests\UpdateExpenseSheetVisibilityRequest;
use App\Http\Requests\UpdateOpenTripDetailsRequest;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use RuntimeException;

/**
 * Owner-only controls for turning a trip into an open trip: publishing,
 * group details, and the trip's expense sheet visibility.
 */
class OpenTripSettingsController extends Controller
{
    public function publish(Trip $trip, PublishOpenTrip $publishOpenTrip): RedirectResponse
    {
        $this->authorize('publish', $trip);

        try {
            $publishOpenTrip->publish($trip, request()->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['visibility' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trip published. Anyone can now discover it.')]);

        return back();
    }

    public function unpublish(Trip $trip, PublishOpenTrip $publishOpenTrip): RedirectResponse
    {
        $this->authorize('publish', $trip);

        $publishOpenTrip->unpublish($trip);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trip is private again.')]);

        return back();
    }

    public function updateDetails(UpdateOpenTripDetailsRequest $request, Trip $trip): RedirectResponse
    {
        $trip->update(['open_trip' => [
            ...$trip->openTripDetails(),
            ...$request->validated(),
        ]]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group details updated.')]);

        return back();
    }

    public function updateExpenseSheetVisibility(UpdateExpenseSheetVisibilityRequest $request, Trip $trip): RedirectResponse
    {
        $trip->update(['expense_sheet_visibility' => $request->validated('expense_sheet_visibility')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense sheet visibility updated.')]);

        return back();
    }
}
