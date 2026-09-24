<?php

namespace App\Notifications\Payments;

use App\Models\Subscription;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells operators that a subscription checkout given up on here was
 * completed at the gateway after all, and has been undone.
 *
 * It happens when a customer leaves a PayPal approval page open (PayPal cannot
 * withdraw one), starts again, and later approves the first. The gateway
 * began billing a subscription this application had already expired, so it is
 * cancelled at the gateway and its payments are refunded.
 */
class AbandonedSubscriptionCanceled extends PaymentNotification
{
    public function __construct(public readonly Subscription $subscription)
    {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->mailMessage(__('An abandoned subscription was cancelled'))
            ->greeting(__('An abandoned subscription was cancelled'))
            ->line(__(':customer completed a :gateway subscription checkout for :plan after it had been given up on here, so :gateway started billing it.', [
                'customer' => $this->subscription->user->email ?? __('a former customer'),
                'gateway' => $this->subscription->gateway->label(),
                'plan' => $this->subscription->plan->name ?? __('a plan'),
            ]))
            ->line(__('It has been cancelled at :gateway and anything it charged is being refunded in full. Nothing needs doing unless a refund fails, which the admin panel would show.', [
                'gateway' => $this->subscription->gateway->label(),
            ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['subscription' => $this->subscription->uuid];
    }
}
