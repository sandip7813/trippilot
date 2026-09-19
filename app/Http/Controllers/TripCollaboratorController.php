<?php

namespace App\Http\Controllers;

use App\Actions\Trips\AddTripCollaborator;
use App\Actions\Trips\RemoveTripCollaborator;
use App\Actions\Trips\UpdateTripCollaboratorRole;
use App\Enums\TripCollaboratorRole;
use App\Http\Requests\StoreTripCollaboratorRequest;
use App\Http\Requests\UpdateTripCollaboratorRequest;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use RuntimeException;

class TripCollaboratorController extends Controller
{
    public function store(StoreTripCollaboratorRequest $request, Trip $trip, AddTripCollaborator $addTripCollaborator): RedirectResponse
    {
        $this->authorize('manageCollaborators', $trip);

        $email = $request->validated('email');
        $role = TripCollaboratorRole::from($request->validated('role'));

        try {
            $addTripCollaborator($trip, $email, $role);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['email' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':email was added to this trip.', ['email' => $email]),
        ]);

        return back();
    }

    public function update(UpdateTripCollaboratorRequest $request, Trip $trip, string $email, UpdateTripCollaboratorRole $updateTripCollaboratorRole): RedirectResponse
    {
        $this->authorize('manageCollaborators', $trip);

        $updateTripCollaboratorRole($trip, $email, TripCollaboratorRole::from($request->validated('role')));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Collaborator role updated.')]);

        return back();
    }

    public function destroy(Trip $trip, string $email, RemoveTripCollaborator $removeTripCollaborator): RedirectResponse
    {
        $this->authorize('manageCollaborators', $trip);

        $removeTripCollaborator($trip, $email);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Collaborator removed.')]);

        return back();
    }
}
