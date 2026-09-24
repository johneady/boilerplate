{{--
    The public plan list.

    The layout comes from the component's render(). Prices shown are the
    plans' own; subscribing re-reads the price server-side.
--}}
<main class="flex-1 py-12">
    <div class="mx-auto max-w-5xl">
        <h1 class="text-4xl font-semibold tracking-tight text-balance">{{ __('Pricing') }}</h1>

        @if (session('subscription_cancelled'))
            <div
                class="mt-8 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
                data-test="subscription-cancelled"
            >
                {{ __('Your subscription was not started. You can choose a plan again below.') }}
            </div>
        @endif

        @error('subscribe')
            <div
                class="mt-8 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200"
                data-test="subscribe-error"
            >
                {{ $message }}
            </div>
        @enderror

        @if ($this->currentSubscription !== null)
            <div class="mt-8 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-700 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300" data-test="already-subscribed">
                {{ __('You are subscribed to :plan.', ['plan' => $this->currentSubscription->plan?->name]) }}
                <a href="{{ route('billing.edit') }}" class="font-medium underline" wire:navigate>{{ __('Manage your subscription') }}</a>
            </div>
        @endif

        @if ($this->plans->isEmpty() || $this->gateways === [])
            <p class="mt-8 text-neutral-600 dark:text-neutral-400" data-test="no-plans">
                {{ __('There are no plans available at the moment.') }}
            </p>
        @else
            @if (count($this->gateways) > 1 && $this->currentSubscription === null)
                <flux:radio.group wire:model="gateway" :label="__('Pay with')" class="mt-8">
                    @foreach ($this->gateways as $gateway)
                        <flux:radio
                            wire:key="gateway-{{ $gateway->value }}"
                            :value="$gateway->value"
                            :label="match ($gateway) {
                                \App\Payments\Enums\Gateway::Stripe => __('Card, Apple Pay or Google Pay'),
                                \App\Payments\Enums\Gateway::PayPal => __('PayPal'),
                                default => __('Demo payment (no money is taken)'),
                            }"
                        />
                    @endforeach
                </flux:radio.group>
            @endif

            <div class="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->plans as $plan)
                    <section
                        class="flex flex-col rounded-lg border border-neutral-200 p-6 dark:border-neutral-800"
                        wire:key="plan-{{ $plan->id }}"
                        data-test="plan-{{ $plan->key }}"
                    >
                        <h2 class="text-xl font-semibold">{{ $plan->name }}</h2>

                        @if (filled($plan->description))
                            <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">{{ $plan->description }}</p>
                        @endif

                        @if ($plan->features !== [])
                            <ul class="mt-4 space-y-1 text-sm">
                                @foreach ($plan->features as $feature)
                                    <li wire:key="plan-{{ $plan->id }}-feature-{{ $loop->index }}">{{ $feature }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($plan->trial_days > 0)
                            <p class="mt-4 text-sm font-medium">
                                {{ trans_choice(':count-day free trial|:count-day free trial', $plan->trial_days, ['count' => $plan->trial_days]) }}
                            </p>
                        @endif

                        <div class="mt-auto space-y-3 pt-6">
                            @foreach ($plan->prices as $price)
                                <div class="flex items-center justify-between gap-3" wire:key="price-{{ $price->id }}">
                                    <span>
                                        {{ $price->label() }}
                                        @if ($plan->taxable)
                                            <span class="text-xs text-neutral-500">{{ __('plus tax') }}</span>
                                        @endif
                                    </span>

                                    @if ($this->currentSubscription === null)
                                        <flux:button
                                            size="sm"
                                            variant="primary"
                                            wire:click="subscribe({{ $price->id }})"
                                            data-test="subscribe-{{ $price->id }}"
                                        >{{ __('Subscribe') }}</flux:button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <div wire:loading wire:target="subscribe" class="mt-4">
                <flux:text size="sm">{{ __('Opening secure checkout...') }}</flux:text>
            </div>
        @endif
    </div>
</main>
