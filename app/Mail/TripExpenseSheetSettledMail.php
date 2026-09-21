<?php

namespace App\Mail;

use App\Models\Trip;
use App\Models\TripExpenseSheet;
use App\Services\Expenses\ExpenseSheetPresenter;
use App\Support\ExpenseMoney;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TripExpenseSheetSettledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Trip $trip,
        public TripExpenseSheet $sheet,
        public string $participantId,
        public string $settledByName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Expenses settled for \"{$this->trip->title}\"",
        );
    }

    public function content(): Content
    {
        $presenter = app(ExpenseSheetPresenter::class);
        $summary = is_array($this->sheet->settlement_snapshot)
            ? $this->sheet->settlement_snapshot
            : $presenter->summary($this->trip, $this->sheet, $presenter->entries($this->sheet));
        $names = collect($this->sheet->participantList())->pluck('name', 'id');
        $participant = $this->sheet->findParticipant($this->participantId);

        return new Content(
            markdown: 'mail.expenses.settled',
            with: [
                'participantName' => $participant['name'] ?? 'there',
                'tripTitle' => $this->trip->title,
                'settledBy' => $this->settledByName,
                'total' => ExpenseMoney::formatMajor($summary['totals']['net']),
                'balances' => collect($summary['participants'])->map(fn (array $row): array => [
                    'name' => $names->get($row['participant_id'], 'Unknown'),
                    'paid' => ExpenseMoney::formatMajor($row['paid']),
                    'share' => ExpenseMoney::formatMajor($row['share']),
                    'balance' => ExpenseMoney::formatMajor($row['balance']),
                ])->all(),
                'settlements' => collect($summary['settlements'])->map(fn (array $row): string => $names->get($row['from'], 'Unknown').' pays '.$names->get($row['to'], 'Unknown').' '.ExpenseMoney::formatMajor($row['amount']))->all(),
                'tripUrl' => ($participant['user_id'] ?? null) !== null ? $this->tripUrl() : null,
            ],
        );
    }

    private function tripUrl(): string
    {
        return $this->trip->isRoadTrip() ? route('road-trips.show', $this->trip) : route('trips.show', $this->trip);
    }
}
