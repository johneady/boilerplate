<?php

namespace App\Notifications\Payments;

use App\Models\Subscription;
use App\Settings\Settings;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Welcomes a subscriber, once, when their subscription first starts.
 */
class SubscriptionStarted extends PaymentNotification
{
    public function __construct(public readonly Subscription $subscription)
    {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subscription = $this->subscription;
        $settings = app(Settings::class);
        $plan = $subscription->plan->name ?? __('your plan');

        $message = $this->mailMessage(__('Your subscription has started'))
            ->greeting(__('Welcome to :plan', ['plan' => $plan]))
            ->line(__('Your subscription to :plan is now active.', ['plan' => $plan]));

        if ($subscription->trial_ends_at !== null && $subscription->trial_ends_at->isFuture()) {
            $message->line(__('Your free trial ends :date. You will not be charged before then, and you can cancel at any time from your billing page.', [
                'date' => $settings->formatDate($subscription->trial_ends_at),
            ]));
        } elseif ($subscription->price !== null && $subscription->current_period_end !== null) {
            $message->line(__('You are billed :price (plus applicable tax). It next renews :date.', [
                'price' => $subscription->price->label(),
                'date' => $settings->formatDate($subscription->current_period_end),
            ]));
        }

        return $message->action(__('Manage your subscription'), route('billing.edit'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['subscription' => $this->subscription->uuid];
    }
}
