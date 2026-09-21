<?php

namespace App\Mail;

use App\Models\Trip;
use App\Services\Expenses\ExpenseReportBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TripExpenseReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Trip $trip,
        public string $format,
        public string $senderName,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Expense report for \"{$this->trip->title}\"",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.expenses.report',
            with: [
                'tripTitle' => $this->trip->title,
                'senderName' => $this->senderName,
                'note' => $this->note,
                'formatLabel' => strtoupper($this->format),
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $builder = app(ExpenseReportBuilder::class);
        $report = $builder->build($this->trip);
        $filename = $builder->filename($this->trip, $this->format);

        if ($this->format === 'pdf') {
            return [Attachment::fromData(fn (): string => $builder->pdf($report), $filename)->withMime('application/pdf')];
        }

        return [Attachment::fromData(fn (): string => $builder->csv($report), $filename)->withMime('text/csv')];
    }
}
