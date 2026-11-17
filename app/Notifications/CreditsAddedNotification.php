<?php

namespace App\Notifications;

use App\Models\CreditTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a user that TripPilot added credits to their balance, by email and
 * in the notification bell.
 */
class CreditsAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $creditTransactionId,
        public int $credits,
        public int $balanceAfter,
        public ?string $note,
    ) {}

    public static function forGrant(CreditTransaction $grant): self
    {
        return new self($grant->id, $grant->amount, $grant->balance_after, $grant->note);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->headline())
            ->greeting('Good news!')
            ->line("TripPilot added {$this->credits} credits to your account.");

        if (filled($this->note)) {
            $mail->line("Note: {$this->note}");
        }

        return $mail
            ->line("Your balance is now {$this->balanceAfter} credits. Credits never expire.")
            ->action('View credit history', route('credits.history'));
    }

    /**
     * @return array{credit_transaction_id: int, credits_added: int, title: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'credit_transaction_id' => $this->creditTransactionId,
            'credits_added' => $this->credits,
            'title' => $this->headline(),
            'message' => filled($this->note)
                ? "{$this->note} · Balance: {$this->balanceAfter} credits"
                : "Your balance is now {$this->balanceAfter} credits.",
            'url' => route('credits.history', absolute: false),
        ];
    }

    private function headline(): string
    {
        return "{$this->credits} credits were added to your account";
    }
}
