<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Trips\TakedownTrip;
use App\Enums\TripReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\TripReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Moderation queue for reports filed against public open trips.
 */
class TripReportController extends Controller
{
    public function index(): Response
    {
        $this->authorize('moderate', Trip::class);

        $reports = TripReport::query()
            ->where('status', TripReportStatus::Open->value)
            ->orderByDesc('created_at')
            ->get();

        $trips = Trip::query()->whereIn('id', $reports->pluck('trip_id')->unique()->all())->get()->keyBy('id');
        $reporters = User::query()->whereIn('id', $reports->pluck('reporter_id')->unique()->all())->get()->keyBy('id');

        return Inertia::render('admin/trip-reports/Index', [
            'reports' => $reports->map(function (TripReport $report) use ($trips, $reporters): array {
                $trip = $trips->get((string) $report->trip_id);
                $reporter = $reporters->get($report->reporter_id);

                return [
                    'id' => (string) $report->id,
                    'reason' => $report->reason,
                    'message' => $report->message,
                    'created_at' => $report->created_at?->toIso8601String(),
                    'reporter_name' => $reporter?->name,
                    'trip' => $trip === null ? null : [
                        'id' => (string) $trip->id,
                        'title' => $trip->title,
                        'is_public' => $trip->isPublic(),
                        'show_url' => route('open-trips.show', $trip),
                    ],
                ];
            })->values(),
        ]);
    }

    public function takedown(TripReport $report, TakedownTrip $takedownTrip): RedirectResponse
    {
        $this->authorize('moderate', Trip::class);

        $trip = Trip::query()->find((string) $report->trip_id);
        abort_if($trip === null, 404);

        $takedownTrip($trip);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trip unpublished and reports resolved.')]);

        return back();
    }

    public function dismiss(TripReport $report): RedirectResponse
    {
        $this->authorize('moderate', Trip::class);

        $report->update(['status' => TripReportStatus::Reviewed]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report dismissed.')]);

        return back();
    }
}
