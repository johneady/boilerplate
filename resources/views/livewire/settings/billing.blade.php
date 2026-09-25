@php($subscription = $this->subscription)
@php($settings = app(\App\Settings\Settings::class))

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Billing settings') }}</flux:heading>

    <x-settings.layout :heading="__('Billing')" :subheading="__('Your subscription and payments')">
        <div class="space-y-8">
            @if (session('subscription_returned') && $this->settingUp)
                <flux:callout variant="secondary" icon="clock" data-test="subscription-processing">
                    {{ __('Your subscription is being set up. This page will show it once the payment provider confirms it.') }}
                </flux:callout>
            @endif

            @if ($status !== '')
                <flux:callout
                    variant="success"
                    icon="check-circle"
                    data-test="billing-status"
                >{{ $status }}</flux:callout>
            @endif

            @error('billing')
                <flux:callout
                    variant="danger"
                    icon="exclamation-triangle"
                    data-test="billing-error"
                >{{ $message }}</flux:callout>
            @enderror

            @if ($subscription === null && ! (session('subscription_returned') && $this->settingUp))
                <div data-test="no-subscription">
                    <flux:text>{{ __('You do not have a subscription.') }}</flux:text>

                    @if (app(\App\Payments\PaymentManager::class)->enabled())
                        <flux:button
                            class="mt-4"
                            variant="primary"
                            :href="route('payments.pricing')"
                        >{{ __('See the plans') }}</flux:button>
                    @endif
                </div>
            @elseif ($subscription !== null)
                <div class="space-y-2" data-test="subscription">
                    <flux:heading size="lg">{{ $subscription->plan?->name }}</flux:heading>

                    <flux:text>
                        {{ $subscription->price?->label() }} ·
                        <span data-test="subscription-status">{{ __($subscription->status->label()) }}</span>
                    </flux:text>

                    @if ($subscription->status === \App\Payments\Enums\SubscriptionStatus::Trialing && $subscription->trial_ends_at !== null)
                        <flux:text>{{ __('Your free trial ends :date.', ['date' => $settings->formatDate($subscription->trial_ends_at)]) }}</flux:text>
                    @endif

                    @if ($subscription->status === \App\Payments\Enums\SubscriptionStatus::PastDue)
                        <flux:callout variant="danger" icon="exclamation-triangle" data-test="past-due">
                            @if ($subscription->graceEndsAt($this->graceDays)?->isFuture())
                                {{ __('Your last payment failed. Please update your payment method before :date to keep your subscription.', ['date' => $settings->formatDate($subscription->graceEndsAt($this->graceDays))]) }}
                            @else
                                {{ __('Your last payment failed. Please update your payment method to restore your subscription.') }}
                            @endif
                        </flux:callout>
                    @endif

                    @if ($subscription->isCancelScheduled())
                        <flux:text data-test="ends-at">{{ __('Your subscription ends :date.', ['date' => $settings->formatDate($subscription->ends_at)]) }}</flux:text>
                    @elseif ($subscription->status->isLive() && $subscription->status !== \App\Payments\Enums\SubscriptionStatus::Incomplete && $subscription->current_period_end !== null)
                        <flux:text>{{ __('Renews :date.', ['date' => $settings->formatDate($subscription->current_period_end)]) }}</flux:text>
                    @elseif ($subscription->status === \App\Payments\Enums\SubscriptionStatus::Canceled)
                        <flux:text>{{ __('Ended :date.', ['date' => $settings->formatDate($subscription->ends_at)]) }}</flux:text>
                    @endif

                    @if ($subscription->pendingPrice !== null)
                        <flux:text data-test="pending-change">{{ __('Changing to :price, waiting for your approval at :gateway.', ['price' => $subscription->pendingPrice->label(), 'gateway' => $subscription->gateway->label()]) }}</flux:text>
                    @endif
                </div>

                @if (in_array($subscription->status, [\App\Payments\Enums\SubscriptionStatus::Trialing, \App\Payments\Enums\SubscriptionStatus::Active, \App\Payments\Enums\SubscriptionStatus::PastDue], true))
                    <div class="flex flex-wrap gap-3">
                        @if ($subscription->isCancelScheduled())
                            <flux:button
                                wire:click="resume"
                                variant="primary"
                                data-test="resume"
                            >{{ __('Keep my subscription') }}</flux:button>
                        @else
                            <flux:button
                                wire:click="cancel"
                                wire:confirm="{{ __('Cancel your subscription? You keep access until the end of the period you have paid for.') }}"
                                data-test="cancel"
                            >{{ __('Cancel subscription') }}</flux:button>
                        @endif

                        @if ($subscription->gateway !== \App\Payments\Enums\Gateway::Demo)
                            <flux:button
                                wire:click="updatePaymentMethod"
                                data-test="payment-method"
                            >{{ __('Update payment method') }}</flux:button>
                        @endif
                    </div>

                    @if ($this->swapOptions->isNotEmpty())
                        <form wire:submit="swap" class="space-y-3" data-test="swap">
                            <flux:select
                                wire:model="newPriceId"
                                :label="__('Change plan')"
                                :placeholder="__('Choose a plan...')"
                            >
                                @foreach ($this->swapOptions as $option)
                                    <flux:select.option wire:key="swap-{{ $option->id }}" :value="$option->id">
                                        {{ $option->plan?->name }} — {{ $option->label() }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:text size="sm">
                                @if ($subscription->gateway === \App\Payments\Enums\Gateway::PayPal)
                                    {{ __('You will be asked to approve the change at PayPal. The new price applies from your next billing date.') }}
                                @else
                                    {{ __('The change applies now. The difference for the rest of this period is added to, or credited on, your next invoice.') }}
                                @endif
                            </flux:text>

                            <flux:button type="submit" data-test="swap-submit">{{ __('Change plan') }}</flux:button>
                        </form>
                    @endif
                @endif
            @endif

            @if ($this->payments->isNotEmpty())
                <div class="space-y-3" data-test="payments">
                    <flux:heading>{{ __('Payments') }}</flux:heading>

                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($this->payments as $payment)
                            <li
                                class="flex items-center justify-between gap-3 py-2 text-sm"
                                wire:key="payment-{{ $payment->id }}"
                            >
                                <span>
                                    {{ $settings->formatDate($payment->paid_at) }} · {{ $payment->description }}
                                    @if ($payment->receiptNumber() !== null)
                                        <span class="font-mono text-xs text-zinc-500">{{ $payment->receiptNumber() }}</span>
                                    @endif
                                </span>
                                <span class="flex items-center gap-3">
                                    <span>{{ $payment->total()->format() }}</span>
                                    <a href="{{ $payment->receiptUrl() }}" class="underline">{{ __('Receipt') }}</a>
                                    @if (\App\Payments\ReceiptPdf::availableFor($payment))
                                        <a href="{{ $payment->receiptPdfUrl() }}" class="underline">{{ __('PDF') }}</a>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </x-settings.layout>
</section>
