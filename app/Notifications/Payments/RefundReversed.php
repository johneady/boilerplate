<?php

namespace App\Notifications\Payments;

use App\Models\Refund;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells operators a refund the gateway had confirmed has since failed.
 *
 * The money went back to the merchant's balance rather than to the customer
 * (Stripe: a refund to a card closed since), so the customer -- who was told
 * their refund was on its way -- has not been paid back. The ledger and the
 * payment's figures have been corrected; returning the money is for a person.
 */
class RefundReversed extends PaymentNotification
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

        return $this->mailMessage(__('A refund failed after it was made'))
            ->greeting(__('A refund failed after it was made'))
            ->line(__('The :amount refund to :customer for :description was confirmed by :gateway, then failed (:reason). The money is back in your :gateway balance, not with the customer.', [
                'amount' => $refund->money()->format(),
                'customer' => $payment->customer_email,
                'description' => $payment->description,
                'gateway' => $payment->gateway->label(),
                'reason' => $refund->failure_reason ?? __('no reason given'),
            ]))
            ->line(__('The customer was told the refund was on its way. Contact them and return the money another way, or refund the payment again from the admin panel.'))
            ->line(__('Reference: :reference', ['reference' => $payment->uuid]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['refund' => $this->refund->uuid];
    }
}
