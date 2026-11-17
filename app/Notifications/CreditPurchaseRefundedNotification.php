<?php

namespace App\Notifications;

use App\Models\CreditOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a user that a credit pack payment was refunded and how many credits
 * were taken back, by email and in the notification bell.
 */
class CreditPurchaseRefundedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $creditOrderId,
        public string $packName,
        public int $refundedPaise,
        public int $creditsRemoved,
    ) {}

    public static function forOrder(CreditOrder $order, int $refundedPaise, int $creditsRemoved): self
    {
        return new self($order->id, $order->pack_name, $refundedPaise, $creditsRemoved);
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
        return (new MailMessage)
            ->subject("Refund processed: {$this->formattedAmount()}")
            ->line("Your refund of {$this->formattedAmount()} for the {$this->packName} pack has been processed. It can take 5–7 working days to reach your account.")
            ->line($this->creditsLine())
            ->line('Order number: TP-'.$this->creditOrderId)
            ->action('View credit history', route('credits.history'));
    }

    /**
     * @return array{credit_order_id: int, credits_event: string, title: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'credit_order_id' => $this->creditOrderId,
            'credits_event' => 'refunded',
            'title' => "Refund of {$this->formattedAmount()} processed",
            'message' => $this->creditsLine(),
            'url' => route('credits.history', absolute: false),
        ];
    }

    private function creditsLine(): string
    {
        return $this->creditsRemoved > 0
            ? "{$this->creditsRemoved} unused credits from the {$this->packName} pack were removed from your balance."
            : "No credits were removed from your balance for the {$this->packName} pack.";
    }

    private function formattedAmount(): string
    {
        return '₹'.number_format($this->refundedPaise / 100, $this->refundedPaise % 100 === 0 ? 0 : 2);
    }
}
