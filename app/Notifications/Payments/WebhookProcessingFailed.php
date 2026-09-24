<?php

namespace App\Notifications\Payments;

use App\Models\WebhookEvent;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells operators a gateway webhook could not be processed after every retry.
 */
class WebhookProcessingFailed extends PaymentNotification
{
    public function __construct(
        public readonly WebhookEvent $event,
        public readonly string $error,
    ) {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->mailMessage(__('A payment webhook could not be processed'))
            ->greeting(__('A payment webhook could not be processed'))
            ->line(__(':gateway event :type (:id) failed: :error', [
                'gateway' => $this->event->gateway->label(),
                'type' => $this->event->type,
                'id' => $this->event->event_id,
                'error' => $this->error,
            ]))
            ->line(__('The payment it concerns may show an out-of-date status. Retry the event from Payments -> Webhook events once the cause is fixed; the scheduled reconciliation also re-reads in-flight payments.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['event' => $this->event->event_id];
    }
}
