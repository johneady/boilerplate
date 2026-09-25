{{--
    The customer's receipt and payment status page, reached by signed URL.

    Everything shown is read from the payment row and its ledger projections,
    which ReconcilePayment keeps in line with the gateway -- nothing here
    trusts the redirect the customer arrived on.
--}}
@php($status = $payment->status)

<x-layouts::public :title="__('Payment')">
    <main class="flex-1 py-12">
        <div class="mx-auto max-w-xl">
            <h1 class="text-4xl font-semibold tracking-tight text-balance" data-test="payment-heading">
                @switch ($status)
                    @case (\App\Payments\Enums\PaymentStatus::Succeeded)
                    @case (\App\Payments\Enums\PaymentStatus::PartiallyRefunded)
                        {{ __('Payment received') }}
                        @break
                    @case (\App\Payments\Enums\PaymentStatus::Refunded)
                        {{ __('Payment refunded') }}
                        @break
                    @case (\App\Payments\Enums\PaymentStatus::Authorized)
                        {{ __('Payment authorized') }}
                        @break
                    @case (\App\Payments\Enums\PaymentStatus::Pending)
                        {{ __('Payment not completed yet') }}
                        @break
                    @default
                        {{ __('Payment not completed') }}
                @endswitch
            </h1>

            <p class="mt-4 text-base leading-relaxed text-neutral-600 dark:text-neutral-400">
                @switch ($status)
                    @case (\App\Payments\Enums\PaymentStatus::Succeeded)
                    @case (\App\Payments\Enums\PaymentStatus::PartiallyRefunded)
                    @case (\App\Payments\Enums\PaymentStatus::Refunded)
                        {{ __('Thank you, :name. A receipt has been emailed to :email.', ['name' => $payment->customer_name, 'email' => $payment->customer_email]) }}
                        @break
                    @case (\App\Payments\Enums\PaymentStatus::Authorized)
                        {{ __('Your payment method has been authorized. You will only be charged once the order is confirmed.') }}
                        @break
                    @case (\App\Payments\Enums\PaymentStatus::Pending)
                        {{ __('If you finished paying, it may take a moment to be confirmed. Refresh this page shortly.') }}
                        @break
                    @default
                        {{ __('No money was taken.') }}
                @endswitch
            </p>

            <dl class="mt-8 space-y-2 rounded-lg border border-neutral-200 p-4 text-sm dark:border-neutral-800">
                <div class="flex justify-between gap-4">
                    <dt>{{ __('For') }}</dt>
                    <dd class="text-right">{{ $payment->description }}</dd>
                </div>

                @if ($payment->tax_lines !== [])
                    <div class="flex justify-between">
                        <dt>{{ __('Subtotal') }}</dt>
                        <dd>{{ $payment->subtotalMoney()->format() }}</dd>
                    </div>

                    @foreach ($payment->taxLines() as $line)
                        <div class="flex justify-between text-neutral-600 dark:text-neutral-400">
                            <dt>{{ $line->label() }}</dt>
                            <dd>{{ $line->amount->format() }}</dd>
                        </div>
                    @endforeach
                @endif

                <div class="flex justify-between font-semibold">
                    <dt>{{ __('Total') }}</dt>
                    <dd data-test="payment-total">{{ $payment->total()->format() }}</dd>
                </div>

                @foreach ($refunds as $refund)
                    <div class="flex justify-between text-neutral-600 dark:text-neutral-400">
                        <dt>
                            {{ __('Refunded :date', ['date' => app(\App\Settings\Settings::class)->formatDate($refund->created_at)]) }}
                        </dt>
                        <dd>-{{ $refund->money()->format() }}</dd>
                    </div>
                @endforeach

                <div class="flex justify-between gap-4 pt-2 text-xs text-neutral-500">
                    <dt>{{ __('Reference') }}</dt>
                    <dd class="font-mono">{{ $payment->uuid }}</dd>
                </div>
            </dl>

            @if (! $status->isPaid() && $status !== \App\Payments\Enums\PaymentStatus::Authorized && $retryUrl !== null)
                <flux:button :href="$retryUrl" variant="primary" class="mt-8">{{ __('Try again') }}</flux:button>
            @endif
        </div>
    </main>
</x-layouts::public>
