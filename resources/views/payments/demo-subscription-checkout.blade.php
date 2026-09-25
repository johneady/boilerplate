{{--
    The Demo gateway's pretend subscription checkout.

    Stands in for Stripe Checkout or PayPal's approval page. Never reachable
    in production: its routes are only registered where the Demo gateway may
    run.
--}}
<x-layouts::public :title="__('Demo checkout')">
    <main class="flex-1 py-12">
        <div class="mx-auto max-w-md">
            <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                {{ __('This is a demonstration checkout. No money is taken, whatever you choose.') }}
            </div>

            <h1 class="mt-8 text-3xl font-semibold tracking-tight">{{ $subscription->plan?->name }}</h1>

            <p class="mt-4 text-2xl" data-test="demo-price">{{ $subscription->price?->label() }}</p>

            @if ($subscription->trial_days > 0)
                <p class="mt-2 text-zinc-600 dark:text-zinc-400">
                    {{ trans_choice(':count-day free trial|:count-day free trial', $subscription->trial_days, ['count' => $subscription->trial_days]) }}
                </p>
            @endif

            <div class="mt-8 flex flex-wrap gap-3">
                <form method="POST" action="{{ URL::signedRoute('subscriptions.demo.store', $subscription) }}">
                    @csrf
                    <input type="hidden" name="outcome" value="approve" />
                    <flux:button
                        type="submit"
                        variant="primary"
                        data-test="demo-subscribe"
                    >{{ __('Subscribe') }}</flux:button>
                </form>

                <form method="POST" action="{{ URL::signedRoute('subscriptions.demo.store', $subscription) }}">
                    @csrf
                    <input type="hidden" name="outcome" value="cancel" />
                    <flux:button type="submit" variant="ghost">{{ __('Cancel and go back') }}</flux:button>
                </form>
            </div>
        </div>
    </main>
</x-layouts::public>
