{{--
    The customer's order page (ShowOrderController), reached through the
    signed link from checkout. Shows where the order is in the kitchen.
--}}
@php
    use App\Ordering\OrderStatus;
    use App\Ordering\Price;
    use App\Settings\SettingKey;

    $timezone = app(\App\Settings\Settings::class)->string(SettingKey::Timezone);
    $readyAt = $order->ready_at->setTimezone($timezone);
    $steps = [OrderStatus::New, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed];
    $reached = array_search($order->status, $steps, true);
@endphp

<x-layouts::public :title="__('Order :number', ['number' => $order->number])">
    <main class="flex-1 pt-4 pb-16">
        <div class="mx-auto max-w-2xl">
            <div class="rounded-3xl border border-stone-200/80 bg-white p-6 sm:p-8 dark:border-white/10 dark:bg-stone-900">
                @if ($order->status === OrderStatus::Cancelled)
                    <flux:badge color="red">{{ __('Cancelled') }}</flux:badge>
                    <h1 class="font-display mt-4 text-3xl font-semibold">{{ __('This order was cancelled') }}</h1>
                    <p class="mt-2 text-stone-600 dark:text-stone-400">{{ __('If this is a surprise, please give us a call.') }}</p>
                @else
                    <span class="flex size-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-300">
                        <flux:icon.check variant="solid" />
                    </span>
                    <h1 class="font-display mt-4 text-3xl font-semibold" data-test="order-heading">
                        {{ __('Thanks, :name! Your order is in.', ['name' => Str::before($order->customer_name, ' ')]) }}
                    </h1>
                    <p class="mt-2 text-stone-600 dark:text-stone-400">
                        {{ $order->fulfilment === \App\Ordering\Fulfilment::Delivery
                            ? __('We will deliver it to :address at :time.', ['address' => $order->delivery_address, 'time' => $readyAt->format('D j M, g:i a')])
                            : __('It will be ready for pickup at :time.', ['time' => $readyAt->format('D j M, g:i a')]) }}
                        {{ __('Keep this page open, it shows the latest status.') }}
                    </p>

                    {{-- Progress --}}
                    <ol class="mt-8 grid grid-cols-4 gap-2" aria-label="{{ __('Order progress') }}">
                        @foreach ($steps as $index => $step)
                            <li class="text-center">
                                <span @class([
                                    'block h-1.5 rounded-full',
                                    'bg-emerald-700 dark:bg-emerald-400' => $index <= $reached,
                                    'bg-stone-200 dark:bg-white/10' => $index > $reached,
                                ])></span>
                                <span @class([
                                    'mt-2 block text-xs font-medium',
                                    'text-emerald-900 dark:text-emerald-300' => $index <= $reached,
                                    'text-stone-400' => $index > $reached,
                                ])>{{ __($step === OrderStatus::Completed ? 'Collected' : $step->label()) }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif

                <div class="mt-8 flex items-baseline justify-between border-t border-stone-200 pt-6 dark:border-white/10">
                    <h2 class="font-semibold">{{ __('Order :number', ['number' => $order->number]) }}</h2>
                    <span class="text-sm text-stone-500">{{ __($order->fulfilment->label()) }}</span>
                </div>

                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-4">
                            <span><span class="font-semibold tabular-nums">{{ $item->quantity }}×</span> {{ $item->product_name }}</span>
                            <span class="tabular-nums">{{ $item->formattedLineTotal() }}</span>
                        </li>
                    @endforeach
                </ul>

                <dl class="mt-4 space-y-1 border-t border-dashed border-stone-200 pt-4 text-sm dark:border-white/10">
                    <div class="flex justify-between text-stone-600 dark:text-stone-400">
                        <dt>{{ __('Subtotal') }}</dt>
                        <dd class="tabular-nums">{{ Price::format($order->subtotal_cents) }}</dd>
                    </div>
                    @if ($order->fulfilment === \App\Ordering\Fulfilment::Delivery)
                        <div class="flex justify-between text-stone-600 dark:text-stone-400">
                            <dt>{{ __('Delivery') }}</dt>
                            <dd class="tabular-nums">{{ $order->delivery_fee_cents === 0 ? __('Free') : Price::format($order->delivery_fee_cents) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between pt-1 text-base font-semibold">
                        <dt>{{ __('Total, paid on :moment', ['moment' => $order->fulfilment === \App\Ordering\Fulfilment::Delivery ? __('delivery') : __('pickup')]) }}</dt>
                        <dd class="tabular-nums">{{ $order->formattedTotal() }}</dd>
                    </div>
                </dl>

                @if (filled($order->notes))
                    <p class="mt-6 rounded-2xl bg-stone-50 p-4 text-sm text-stone-600 dark:bg-white/5 dark:text-stone-400">
                        <span class="font-semibold text-stone-800 dark:text-stone-200">{{ __('Your note:') }}</span>
                        {{ $order->notes }}
                    </p>
                @endif
            </div>

            <div class="mt-6 text-center">
                <flux:button :href="route('home')" variant="ghost" icon="arrow-left" wire:navigate>{{ __('Back to the menu') }}</flux:button>
            </div>
        </div>
    </main>
</x-layouts::public>
