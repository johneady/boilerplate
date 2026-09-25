<?php

namespace App\Notifications\Payments;

use App\Models\Subscription;
use App\Settings\Settings;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Reminds a subscriber that their free trial ends soon, once, a few days out.
 *
 * Sent by payments:notify-trials-ending for every gateway alike: Stripe's own
 * trial_will_end event is deliberately not used, so Stripe and PayPal
 * subscribers are reminded the same way at the same time.
 */
class TrialEnding extends PaymentNotification
{
    public function __construct(public readonly Subscription $subscription)
    {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subscription = $this->subscription;
        $plan = $subscription->plan->name ?? __('your plan');

        $message = $this->mailMessage(__('Your free trial ends soon'))
            ->greeting(__('Your free trial ends soon'))
            ->line(__('Your free trial of :plan ends :date.', [
                'plan' => $plan,
                'date' => app(Settings::class)->formatDate($subscription->trial_ends_at),
            ]));

        if ($subscription->price !== null) {
            $message->line(__('After that you will be billed :price (plus applicable tax) unless you cancel before then.', ['price' => $subscription->price->label()]));
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
