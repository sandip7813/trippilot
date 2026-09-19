<?php

namespace App\Console\Commands;

use App\Models\Trip;
use App\Models\User;
use App\Notifications\TripReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class SendTripReminders extends Command
{
    protected $signature = 'trippilot:send-trip-reminders';

    protected $description = 'Send email and in-app reminders for upcoming trips';

    public function handle(): int
    {
        /** @var list<int> $reminderDays */
        $reminderDays = collect(config('trippilot.reminder_days', [7, 1]))
            ->map(fn (mixed $days): int => (int) $days)
            ->sortDesc()
            ->values()
            ->all();

        if ($reminderDays === []) {
            return self::SUCCESS;
        }

        $today = now()->startOfDay();

        $trips = Trip::query()
            ->active()
            ->where('start_date', '>=', $today)
            ->where('start_date', '<=', $today->copy()->addDays($reminderDays[0])->endOfDay())
            ->get();

        $sent = 0;

        foreach ($trips as $trip) {
            $daysUntil = (int) $today->diffInDays($trip->start_date->copy()->startOfDay());
            $alreadySent = collect($trip->reminders_sent ?? [])->map(fn (mixed $days): int => (int) $days);
            $due = collect($reminderDays)
                ->filter(fn (int $days): bool => $daysUntil <= $days && ! $alreadySent->contains($days));

            if ($due->isEmpty()) {
                continue;
            }

            $trip->update(['reminders_sent' => $alreadySent->merge($due)->unique()->values()->all()]);

            Notification::send($this->recipients($trip), TripReminderNotification::forTrip($trip, $daysUntil));
            $sent++;
        }

        $this->components->info("Sent reminders for {$sent} trip(s).");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Trip $trip): Collection
    {
        $ids = collect($trip->collaboratorEntries())
            ->where('status', 'accepted')
            ->pluck('user_id')
            ->filter()
            ->push($trip->user_id)
            ->unique();

        return User::query()->whereIn('id', $ids)->get();
    }
}
