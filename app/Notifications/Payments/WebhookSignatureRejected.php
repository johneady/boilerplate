<?php

namespace App\Notifications\Payments;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells operators webhook deliveries are being rejected.
 *
 * Either the signing secret is wrong -- so real payment updates are being
 * dropped -- or someone is posting forged events. Both need a person.
 */
class WebhookSignatureRejected extends PaymentNotification
{
    public function __construct(
        public readonly string $gateway,
        public readonly string $reason,
    ) {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->mailMessage(__('Payment webhooks are being rejected'))
            ->greeting(__('Payment webhooks are being rejected'))
            ->line(__('Several :gateway webhook deliveries in the last hour failed verification. The most recent: :reason', [
                'gateway' => $this->gateway,
                'reason' => $this->reason,
            ]))
            ->line(__('If the signing secret or webhook ID in the payment settings is wrong, real payment updates are being dropped: fix it, then the gateway\'s own retries will deliver them.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['gateway' => $this->gateway];
    }
}
