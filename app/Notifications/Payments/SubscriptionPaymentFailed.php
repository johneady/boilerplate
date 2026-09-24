<?php

namespace App\Notifications\Payments;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use App\Payments\PaymentManager;
use App\Settings\Settings;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * A subscription renewal failed and the subscription is past due.
 *
 * Sent once per episode -- on the move into past due -- to the subscriber,
 * with a link to fix the payment method, and to operators. The gateway keeps
 * retrying; the subscriber keeps access for the grace period meanwhile.
 */
class SubscriptionPaymentFailed extends PaymentNotification
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly bool $forOperators = false,
    ) {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subscription = $this->subscription;
        $plan = $subscription->plan->name ?? __('your plan');
        $graceEnds = $subscription->graceEndsAt(app(PaymentManager::class)->graceDays());
        $until = $graceEnds !== null ? app(Settings::class)->formatDate($graceEnds) : null;

        if ($this->forOperators) {
            return $this->mailMessage(__('A subscription renewal failed'))
                ->greeting(__('A subscription renewal failed'))
                ->line(__('The renewal payment for :customer\'s :plan subscription failed. :gateway will retry it.', [
                    'customer' => $subscription->user->email ?? __('a former customer'),
                    'plan' => $plan,
                    'gateway' => $subscription->gateway->label(),
                ]))
                ->line($until !== null ? __('They keep access until :date unless a retry succeeds.', ['date' => $until]) : __('Their access has already lapsed.'))
                ->action(__('View the subscription'), SubscriptionResource::getUrl('view', ['record' => $subscription], panel: 'admin'));
        }

        return $this->mailMessage(__('Your payment failed'))
            ->greeting(__('We could not take your payment'))
            ->line(__('The latest payment for your :plan subscription did not go through. We will try again over the next few days.', ['plan' => $plan]))
            ->line($until !== null
                ? __('To keep your subscription, please check or update your payment method before :date.', ['date' => $until])
                : __('Please check or update your payment method to restore your subscription.'))
            ->action(__('Update payment method'), route('billing.edit'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['subscription' => $this->subscription->uuid, 'for_operators' => $this->forOperators];
    }
}
