<?php

namespace App\Notifications\Payments;

use App\Models\Subscription;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells operators that a customer has two subscriptions running at once.
 *
 * Only the gateway can produce one: a checkout the customer left open and
 * completed after starting another. Both are billing the customer, so an
 * operator decides which to cancel and refunds what it took. Nothing is
 * cancelled automatically, because both subscriptions were paid for.
 */
class DuplicateSubscriptionDetected extends PaymentNotification
{
    public function __construct(public readonly Subscription $subscription)
    {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->mailMessage(__('A customer has two subscriptions'))
            ->greeting(__('A customer has two subscriptions'))
            ->line(__(':customer has a second :gateway subscription running, to :plan, alongside the one they already had, so they are being billed twice.', [
                'customer' => $this->subscription->user->email ?? __('a customer'),
                'gateway' => $this->subscription->gateway->label(),
                'plan' => $this->subscription->plan->name ?? __('a plan'),
            ]))
            ->line(__('Cancel the one they did not mean to keep from the admin panel, and refund what it charged.'))
            ->line(__('Reference: :reference', ['reference' => $this->subscription->uuid]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['subscription' => $this->subscription->uuid];
    }
}
