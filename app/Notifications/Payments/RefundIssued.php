<?php

namespace App\Notifications\Payments;

use App\Models\Refund;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the customer money is on its way back, once a refund is confirmed.
 */
class RefundIssued extends PaymentNotification
{
    public function __construct(public readonly Refund $refund)
    {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $refund = $this->refund;
        // The loaded relation when there is one (the mail preview builds it in
        // memory, with nothing in the database behind it).
        $payment = $refund->payment ?? $refund->payment()->firstOrFail();

        $message = $this->mailMessage(__('Refund issued'))
            ->greeting(__('Hello :name', ['name' => $payment->customer_name]))
            ->line(__('We have refunded :amount of your payment for :description.', [
                'amount' => $refund->money()->format(),
                'description' => $payment->description,
            ]));

        if ($refund->tax_amount > 0) {
            $message->line(__('Of this, :amount is tax.', ['amount' => $refund->taxMoney()->format()]));
        }

        return $message
            ->line(__('Depending on your bank, it can take several business days to appear on your statement.'))
            ->action(__('View your receipt'), $payment->receiptUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['refund' => $this->refund->uuid];
    }
}
