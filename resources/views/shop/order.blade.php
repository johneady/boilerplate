{{--
    The order confirmation, reached through the signed URL checkout redirects
    to -- see App\Http\Controllers\OrderController for why it is signed.
--}}
<x-layouts::public :title="__('Order :reference', ['reference' => $order->reference])">
    <main class="flex-1 py-12">
        <div class="mx-auto max-w-3xl">
            <div class="flex items-center gap-3">
                <span class="flex size-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
                    <flux:icon.check variant="solid" class="size-6" />
                </span>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Order :reference', ['reference' => $order->reference]) }}
                    </p>
                    <h1 class="text-3xl font-semibold tracking-tight">
                        {{ __('Thank you, :name!', ['name' => $order->customer_name]) }}
                    </h1>
                </div>
            </div>

            <p class="mt-6 text-neutral-600 dark:text-neutral-400">
                {{ __('Your order is confirmed. Your download links and licence certificate have been sent to :email.', ['email' => $order->customer_email]) }}
            </p>

            <ul class="mt-8 divide-y divide-neutral-200 rounded-2xl border border-neutral-200 bg-white dark:divide-neutral-800 dark:border-neutral-800 dark:bg-neutral-900">
                @foreach ($order->items as $item)
                    <li class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
                        <div class="w-full shrink-0 overflow-hidden rounded-lg bg-neutral-100 sm:w-36 dark:bg-neutral-800">
                            @if ($item->package?->imageUrl() !== null)
                                <img
                                    src="{{ $item->package->imageUrl() }}"
                                    alt=""
                                    class="aspect-16/10 size-full object-cover"
                                />
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ $item->package_title }}</p>
                            @if ($item->package !== null)
                                <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">
                                    {{ $item->package->resolution }} · {{ trans_choice(':count clip|:count clips', $item->package->clip_count, ['count' => $item->package->clip_count]) }} · {{ $item->package->formattedDuration() }}
                                </p>
                            @endif
                        </div>

                        <div class="flex items-center gap-4">
                            <p class="font-semibold">{{ $item->formattedPrice() }}</p>
                            <flux:tooltip :content="__('Enabled once the original footage files are uploaded.')">
                                <div>
                                    <flux:button
                                        size="sm"
                                        icon="arrow-down-tray"
                                        disabled
                                    >{{ __('Download') }}</flux:button>
                                </div>
                            </flux:tooltip>
                        </div>
                    </li>
                @endforeach
            </ul>

            <dl class="mt-6 flex justify-between border-t border-neutral-200 pt-4 text-lg font-semibold dark:border-neutral-800">
                <dt>{{ __('Total paid') }}</dt>
                <dd>{{ $order->formattedTotal() }}</dd>
            </dl>

            <div class="mt-10 flex flex-wrap gap-3">
                <flux:button
                    variant="primary"
                    :href="route('shop.index')"
                    wire:navigate
                >{{ __('Browse more footage') }}</flux:button>
                <flux:button
                    variant="ghost"
                    :href="route('contact')"
                    wire:navigate
                >{{ __('Questions about your order?') }}</flux:button>
            </div>
        </div>
    </main>
</x-layouts::public>
