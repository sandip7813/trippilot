<?php

namespace App\Http\Controllers;

use App\Actions\Trips\ReplyToTripInquiry;
use App\Actions\Trips\SendTripInquiry;
use App\Http\Requests\ReplyTripInquiryRequest;
use App\Http\Requests\SendTripInquiryRequest;
use App\Models\Trip;
use App\Models\TripInquiry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class TripInquiryController extends Controller
{
    /**
     * The owner's inbox: every inquiry thread on this trip.
     */
    public function index(Trip $trip): Response
    {
        $this->authorize('manageInquiries', $trip);

        $inquiries = TripInquiry::query()
            ->where('trip_id', (string) $trip->id)
            ->orderByDesc('updated_at')
            ->get();

        $senders = User::query()->whereIn('id', $inquiries->pluck('sender_id')->unique()->all())->get()->keyBy('id');

        return Inertia::render('Trips/Inquiries', [
            'trip' => ['id' => (string) $trip->id, 'title' => $trip->title],
            'inquiries' => $this->inquiriesForFrontend($inquiries, $senders),
        ]);
    }

    public function store(SendTripInquiryRequest $request, Trip $trip, SendTripInquiry $sendTripInquiry): RedirectResponse
    {
        try {
            $sendTripInquiry($trip, $request->user(), $request->validated('subject'), $request->validated('body'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['body' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Message sent to the organizer.')]);

        return back();
    }

    public function reply(ReplyTripInquiryRequest $request, Trip $trip, TripInquiry $inquiry, ReplyToTripInquiry $replyToTripInquiry): RedirectResponse
    {
        abort_unless((string) $inquiry->trip_id === (string) $trip->id, 404);
        abort_unless($trip->isOwnedBy($request->user()) || $inquiry->involves($request->user()), 403);

        try {
            $replyToTripInquiry($trip, $inquiry, $request->user(), $request->validated('body'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['body' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reply sent.')]);

        return back();
    }

    /**
     * @param  Collection<int, TripInquiry>  $inquiries
     * @param  Collection<int, User>  $senders
     * @return list<array<string, mixed>>
     */
    private function inquiriesForFrontend(Collection $inquiries, Collection $senders): array
    {
        return $inquiries->map(function (TripInquiry $inquiry) use ($senders): array {
            $sender = $senders->get((int) $inquiry->sender_id);

            return [
                'id' => (string) $inquiry->id,
                'subject' => $inquiry->subject,
                'sender_name' => $sender?->name ?? 'Deleted user',
                'messages' => $inquiry->messageList(),
                'updated_at' => $inquiry->updated_at?->toIso8601String(),
            ];
        })->values()->all();
    }
}
