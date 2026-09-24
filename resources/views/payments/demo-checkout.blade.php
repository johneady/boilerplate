{{--
    The Demo gateway's pretend hosted checkout.

    Stands in for Stripe's or PayPal's page so a demonstration can walk
    through a payment without credentials. Never reachable in production:
    its routes are only registered where the Demo gateway may run.
--}}
<x-layouts::public :title="__('Demo checkout')">
    <main class="flex-1 py-12">
        <div class="mx-auto max-w-md">
            <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                {{ __('This is a demonstration checkout. No money is taken, whatever you choose.') }}
            </div>

            <h1 class="mt-8 text-3xl font-semibold tracking-tight">{{ $payment->description }}</h1>

            <p class="mt-4 text-2xl" data-test="demo-amount">{{ $payment->total()->format() }}</p>

            <div class="mt-8 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('payments.demo.store', $payment) }}">
                    @csrf
                    <input type="hidden" name="outcome" value="approve" />
                    <flux:button type="submit" variant="primary" data-test="demo-approve">
                        {{ $payment->capture_method === \App\Payments\Enums\CaptureMethod::Manual ? __('Authorize payment') : __('Pay now') }}
                    </flux:button>
                </form>

                <form method="POST" action="{{ route('payments.demo.store', $payment) }}">
                    @csrf
                    <input type="hidden" name="outcome" value="decline" />
                    <flux:button type="submit" variant="danger">{{ __('Decline card') }}</flux:button>
                </form>

                <form method="POST" action="{{ route('payments.demo.store', $payment) }}">
                    @csrf
                    <input type="hidden" name="outcome" value="cancel" />
                    <flux:button type="submit" variant="ghost">{{ __('Cancel and go back') }}</flux:button>
                </form>
            </div>
        </div>
    </main>
</x-layouts::public>
