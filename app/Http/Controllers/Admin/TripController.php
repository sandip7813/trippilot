<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerateTripStatusRequest;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('moderate', Trip::class);

        $status = $request->string('status', 'all')->toString();
        $type = $request->string('type', 'all')->toString();
        $search = trim($request->string('search')->toString());

        $query = Trip::query()->orderByDesc('created_at');

        $query = match ($status) {
            'archived' => $query->archived(),
            'draft' => $query->where('status', TripStatus::Draft->value),
            'planned' => $query->where('status', TripStatus::Planned->value),
            default => $query,
        };

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        if ($search !== '') {
            $query->where('title', 'regex', '/'.preg_quote($search, '/').'/i');
        }

        $trips = $query->paginate(20)->withQueryString();

        $owners = User::query()
            ->whereIn('id', $trips->getCollection()->pluck('user_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $trips->through(fn (Trip $trip): array => $this->tripPayload($trip, $owners));

        return Inertia::render('admin/trips/Index', [
            'trips' => $trips,
            'filters' => [
                'status' => $status,
                'type' => $type,
                'search' => $search,
            ],
            'counts' => $this->counts(),
        ]);
    }

    public function updateStatus(ModerateTripStatusRequest $request, Trip $trip): RedirectResponse
    {
        $this->authorize('moderate', Trip::class);

        $trip->update([
            'status' => $request->enum('status', TripStatus::class),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Trip status updated.'),
        ]);

        return back();
    }

    public function destroy(Trip $trip): RedirectResponse
    {
        $this->authorize('delete', $trip);

        $trip->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Trip deleted.'),
        ]);

        return back();
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'all' => Trip::query()->count(),
            'active' => Trip::query()->active()->count(),
            'archived' => Trip::query()->archived()->count(),
            'vacation' => Trip::query()->where('type', TripType::Vacation->value)->count(),
            'road' => Trip::query()->where('type', TripType::Road->value)->count(),
        ];
    }

    /**
     * @param  Collection<int, User>  $owners
     * @return array<string, mixed>
     */
    private function tripPayload(Trip $trip, $owners): array
    {
        $destination = Trip::normalizeLocation($trip->getAttribute('destination'));
        $owner = $owners->get($trip->user_id);

        return [
            'id' => (string) $trip->id,
            'title' => $trip->title,
            'type' => $trip->type->value,
            'type_label' => $trip->type->label(),
            'status' => $trip->status->value,
            'status_label' => $trip->status->label(),
            'destination_label' => $destination['label'] ?? null,
            'start_date' => $trip->start_date?->toDateString(),
            'end_date' => $trip->end_date?->toDateString(),
            'created_at' => $trip->created_at?->toIso8601String(),
            'owner' => [
                'id' => $trip->user_id,
                'name' => $owner?->name,
                'email' => $owner?->email,
            ],
            'show_url' => $trip->isRoadTrip()
                ? route('road-trips.show', $trip)
                : route('trips.show', $trip),
        ];
    }
}
