<?php

namespace App\Notifications;

use App\Models\CreditOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirms a credit pack purchase by email (a simple receipt) and in the
 * notification bell.
 */
class CreditsPurchasedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $creditOrderId,
        public string $packName,
        public int $credits,
        public int $amountPaise,
        public ?string $paymentId,
        public int $balanceAfter,
    ) {}

    public static function forOrder(CreditOrder $order, int $balanceAfter): self
    {
        return new self(
            $order->id,
            $order->pack_name,
            $order->credits,
            $order->amount_paise,
            $order->razorpay_payment_id,
            $balanceAfter,
        );
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
            ->subject("Payment received: {$this->credits} credits added")
            ->greeting('Thank you for your purchase!')
            ->line("We received your payment of {$this->formattedAmount()} for the {$this->packName} pack.")
            ->line("{$this->credits} credits were added to your account. Your balance is now {$this->balanceAfter} credits. Credits never expire.")
            ->line('Order number: TP-'.$this->creditOrderId)
            ->line('Payment ID: '.($this->paymentId ?? '—'))
            ->action('View credit history', route('credits.history'));
    }

    /**
     * @return array{credit_order_id: int, credits_event: string, title: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'credit_order_id' => $this->creditOrderId,
            'credits_event' => 'purchased',
            'title' => "{$this->credits} credits added to your account",
            'message' => "Payment of {$this->formattedAmount()} received for the {$this->packName} pack. Balance: {$this->balanceAfter} credits.",
            'url' => route('credits.history', absolute: false),
        ];
    }

    private function formattedAmount(): string
    {
        return '₹'.number_format($this->amountPaise / 100, $this->amountPaise % 100 === 0 ? 0 : 2);
    }
}
