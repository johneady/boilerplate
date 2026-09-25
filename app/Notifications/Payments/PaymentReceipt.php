<?php

namespace App\Notifications\Payments;

use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The customer's receipt, sent once when a payment first succeeds.
 */
class PaymentReceipt extends PaymentNotification
{
    public function __construct(public readonly Payment $payment)
    {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->payment;

        $message = $this->mailMessage(__('Payment received'))
            ->greeting(__('Thank you, :name', ['name' => $payment->customer_name]))
            ->line(__('We received your payment for :description.', ['description' => $payment->description]))
            ->line(__('Subtotal: :amount', ['amount' => $payment->subtotalMoney()->format()]));

        foreach ($payment->taxLines() as $line) {
            $message->line(__(':tax: :amount', [
                'tax' => $line->label(),
                'amount' => $line->amount->format(),
            ]));
        }

        return $message
            ->line(__('Total paid: :amount', ['amount' => $payment->capturedMoney()->isZero() ? $payment->total()->format() : $payment->capturedMoney()->format()]))
            ->line(__('Reference: :reference', ['reference' => $payment->uuid]))
            ->action(__('View your receipt'), $payment->receiptUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['payment' => $this->payment->uuid];
    }
}
