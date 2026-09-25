<?php

namespace App\Notifications\Payments;

use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells operators a second payment for something already paid is being refunded.
 */
class DuplicatePaymentRefunded extends PaymentNotification
{
    public function __construct(public readonly Payment $payment)
    {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->mailMessage(__('Duplicate payment refunded'))
            ->greeting(__('Duplicate payment refunded'))
            ->line(__(':customer paid :amount for :description, which had already been paid.', [
                'customer' => $this->payment->customer_name,
                'amount' => $this->payment->total()->format(),
                'description' => $this->payment->description,
            ]))
            ->line(__('The second payment is being refunded in full automatically. Nothing needs doing unless the refund fails, which the admin panel would show.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['payment' => $this->payment->uuid];
    }
}
