<?php

namespace App\Notifications\Payments;

use App\Models\Payment;
use App\Models\Subscription;
use App\Settings\Settings;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The receipt for a subscription payment -- the first one and every renewal.
 *
 * Sent in place of PaymentReceipt when the payment is a subscription's, and
 * claimed the same way (receipt_sent_at), so each payment has one receipt.
 */
class SubscriptionRenewed extends PaymentNotification
{
    public function __construct(public readonly Payment $payment)
    {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->payment;
        $subscription = $payment->payable;

        $message = $this->mailMessage(__('Subscription payment received'))
            ->greeting(__('Thank you, :name', ['name' => $payment->customer_name]))
            ->line(__('We received your payment for :description.', ['description' => $payment->description]))
            ->line(__('Subtotal: :amount', ['amount' => $payment->subtotalMoney()->format()]));

        foreach ($payment->taxLines() as $line) {
            $message->line($this->taxLineText($line));
        }

        $message->line(__('Total paid: :amount', ['amount' => $payment->total()->format()]));

        if ($subscription instanceof Subscription && $subscription->current_period_end !== null && ! $subscription->cancel_at_period_end) {
            $message->line(__('Your subscription next renews :date.', ['date' => app(Settings::class)->formatDate($subscription->current_period_end)]));
        }

        if ($payment->receiptNumber() !== null) {
            $message->line(__('Receipt number: :number', ['number' => $payment->receiptNumber()]));
        }

        return $message
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
