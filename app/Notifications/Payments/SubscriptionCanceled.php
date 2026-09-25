<?php

namespace App\Notifications\Payments;

use App\Models\Subscription;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells a subscriber, once, that their subscription has ended.
 */
class SubscriptionCanceled extends PaymentNotification
{
    public function __construct(public readonly Subscription $subscription)
    {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->subscription->plan->name ?? __('your plan');

        return $this->mailMessage(__('Your subscription has ended'))
            ->greeting(__('Your subscription has ended'))
            ->line(__('Your subscription to :plan has ended, and you will not be charged again.', ['plan' => $plan]))
            ->line(__('You are welcome back at any time.'))
            ->action(__('See the plans'), route('payments.pricing'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['subscription' => $this->subscription->uuid];
    }
}
