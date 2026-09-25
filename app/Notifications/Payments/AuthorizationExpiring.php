<?php

namespace App\Notifications\Payments;

use App\Models\Payment;
use App\Settings\Settings;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Warns operators that a held payment will be released unless captured soon.
 */
class AuthorizationExpiring extends PaymentNotification
{
    public function __construct(public readonly Payment $payment)
    {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->mailMessage(__('A payment hold is about to expire'))
            ->greeting(__('A payment hold is about to expire'))
            ->line(__('The :amount hold for :description (:customer) expires :when.', [
                'amount' => $this->payment->total()->format(),
                'description' => $this->payment->description,
                'customer' => $this->payment->customer_name,
                'when' => app(Settings::class)->formatDateTime($this->payment->authorization_expires_at),
            ]))
            ->line(__('Capture it from the admin panel before then, or the customer\'s funds are released and it can no longer be taken.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['payment' => $this->payment->uuid];
    }
}
