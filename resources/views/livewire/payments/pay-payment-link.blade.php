@php($link = $this->paymentLink)
@php($breakdown = $this->breakdown)

{{--
    The public payment page for a payment link.

    The layout comes from the component's render(). The price shown here is a
    preview; what is charged is recomputed server-side by StartCheckout from
    the link itself, never taken from this form.
--}}
<main class="flex-1 py-12">
    <div class="mx-auto max-w-xl">
        <h1 class="text-4xl font-semibold tracking-tight text-balance">{{ $link->title }}</h1>

        @if (filled($link->description))
            {{-- Administrator-written plain text: escaped, line breaks kept. --}}
            <p class="mt-6 text-base leading-relaxed whitespace-pre-line text-neutral-600 dark:text-neutral-400">
                {{ $link->description }}
            </p>
        @endif

        @if (session('payment_cancelled'))
            <div
                class="mt-8 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
                data-test="payment-cancelled"
            >
                {{ __('Your payment was not completed. You can try again below.') }}
            </div>
        @endif

        @if (! $link->acceptsPayments())
            <div
                class="mt-8 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-700 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300"
                data-test="link-closed"
            >
                {{ __('This payment link is no longer accepting payments.') }}
            </div>
        @elseif ($this->gateways === [])
            <div
                class="mt-8 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-700 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300"
                data-test="no-gateways"
            >
                {{ __('Online payment is not available at the moment. Please contact us to pay.') }}
            </div>
        @else
            <form wire:submit="pay" class="mt-8 space-y-6">
                @if ($this->isCustomerEntered())
                    <flux:input
                        wire:model.live.debounce.400ms="amount"
                        :label="__('Amount (:currency)', ['currency' => $link->currency->value])"
                        inputmode="decimal"
                        required
                        :description="match (true) {
                            $link->minimum() !== null && $link->maximum() !== null => __('Between :min and :max.', ['min' => $link->minimum()->format(), 'max' => $link->maximum()->format()]),
                            $link->minimum() !== null => __('At least :min.', ['min' => $link->minimum()->format()]),
                            $link->maximum() !== null => __('Up to :max.', ['max' => $link->maximum()->format()]),
                            default => null,
                        }"
                    />
                @endif

                @if ($breakdown !== null)
                    <dl
                        class="space-y-2 rounded-lg border border-neutral-200 p-4 text-sm dark:border-neutral-800"
                        data-test="price-breakdown"
                    >
                        @if ($breakdown->lines !== [])
                            <div class="flex justify-between">
                                <dt>{{ __('Subtotal') }}</dt>
                                <dd>{{ $breakdown->subtotal->format() }}</dd>
                            </div>

                            @foreach ($breakdown->lines as $line)
                                <div
                                    class="flex justify-between text-neutral-600 dark:text-neutral-400"
                                    wire:key="tax-{{ $loop->index }}"
                                >
                                    <dt>{{ $line->label() }}</dt>
                                    <dd>{{ $line->amount->format() }}</dd>
                                </div>
                            @endforeach
                        @endif

                        <div class="flex justify-between text-base font-semibold">
                            <dt>{{ __('Total') }}</dt>
                            <dd>{{ $breakdown->total()->format() }}</dd>
                        </div>
                    </dl>
                @endif

                <flux:input wire:model="name" :label="__('Your name')" type="text" required autocomplete="name" />

                <flux:input
                    wire:model="email"
                    :label="__('Your email address')"
                    type="email"
                    required
                    autocomplete="email"
                    :description="__('Your receipt is sent here.')"
                />

                <flux:radio.group wire:model="gateway" :label="__('Pay with')">
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

                <div class="flex items-center gap-3">
                    <flux:button
                        type="submit"
                        variant="primary"
                        data-test="pay-button"
                    >{{ __('Continue to payment') }}</flux:button>

                    <div wire:loading wire:target="pay">
                        <flux:text size="sm">{{ __('Opening secure checkout...') }}</flux:text>
                    </div>
                </div>
            </form>
        @endif
    </div>
</main>
