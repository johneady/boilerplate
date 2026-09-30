{{--
    The cart and checkout (App\Livewire\Ordering\Checkout). Quantities change
    in place; the summary on the right is worked out on the server each time.
--}}
<main class="flex-1 pt-4 pb-16">
    <a href="{{ route('home') }}#menu" class="inline-flex items-center gap-1 text-sm font-medium text-stone-600 hover:text-emerald-800 dark:text-stone-400" wire:navigate>
        <flux:icon.arrow-left variant="micro" />
        {{ __('Back to the menu') }}
    </a>

    <h1 class="font-display mt-4 text-4xl font-semibold tracking-tight">{{ __('Your order') }}</h1>

    @if ($this->lines === [])
        <div class="mt-8 rounded-3xl border border-dashed border-stone-300 bg-white/60 p-10 text-center dark:border-white/15 dark:bg-white/5" data-test="empty-cart">
            <flux:icon.shopping-bag class="mx-auto size-10 text-stone-400" />
            <p class="font-display mt-4 text-xl font-semibold">{{ __('Your cart is empty') }}</p>
            <p class="mt-1 text-stone-600 dark:text-stone-400">{{ __('Add something delicious from the menu to get started.') }}</p>
            <flux:button :href="route('home').'#menu'" variant="primary" class="mt-6">{{ __('Browse the menu') }}</flux:button>
        </div>
    @else
        <form wire:submit="placeOrder" class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
            <div class="min-w-0 space-y-8">
                {{-- Cart lines --}}
                <section class="rounded-3xl border border-stone-200/80 bg-white p-2 dark:border-white/10 dark:bg-stone-900">
                    <ul class="divide-y divide-stone-100 dark:divide-white/5">
                        @foreach ($this->lines as $line)
                            <li class="flex items-center gap-3 p-3 sm:gap-4" wire:key="line-{{ $line->product->id }}" data-test="cart-line">
                                <div class="size-14 shrink-0 sm:size-16 overflow-hidden rounded-xl bg-stone-100 dark:bg-stone-800">
                                    @if ($line->product->imageUrl() !== null)
                                        <img src="{{ $line->product->imageUrl() }}" alt="" class="size-full object-cover" />
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold">{{ $line->product->name }}</p>
                                    <p class="text-sm text-stone-500 dark:text-stone-400">{{ $line->product->formattedPrice() }} {{ __('each') }}</p>
                                </div>

                                <div class="flex items-center gap-1 rounded-full border border-stone-200 p-0.5 dark:border-white/10">
                                    <button
                                        type="button"
                                        wire:click="decrement({{ $line->product->id }})"
                                        class="flex size-8 items-center justify-center rounded-full text-stone-600 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-white/10"
                                        aria-label="{{ __('One fewer :product', ['product' => $line->product->name]) }}"
                                    >
                                        <flux:icon.minus variant="micro" />
                                    </button>
                                    <span class="w-6 text-center text-sm font-semibold tabular-nums" data-test="line-quantity">{{ $line->quantity }}</span>
                                    <button
                                        type="button"
                                        wire:click="increment({{ $line->product->id }})"
                                        class="flex size-8 items-center justify-center rounded-full text-stone-600 hover:bg-stone-100 disabled:opacity-40 dark:text-stone-300 dark:hover:bg-white/10"
                                        aria-label="{{ __('One more :product', ['product' => $line->product->name]) }}"
                                        @disabled($line->quantity >= \App\Ordering\Cart::MAX_QUANTITY)
                                    >
                                        <flux:icon.plus variant="micro" />
                                    </button>
                                </div>

                                <p class="w-20 text-right font-semibold tabular-nums max-sm:hidden">{{ $this->formatPrice($line->totalCents()) }}</p>

                                <button
                                    type="button"
                                    wire:click="remove({{ $line->product->id }})"
                                    class="text-stone-400 hover:text-red-600"
                                    aria-label="{{ __('Remove :product', ['product' => $line->product->name]) }}"
                                >
                                    <flux:icon.x-mark variant="mini" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </section>

                {{-- Pickup or delivery --}}
                <section>
                    <h2 class="font-display text-2xl font-semibold">{{ __('Pickup or delivery') }}</h2>

                    <flux:radio.group wire:model.live="fulfilment" variant="cards" class="mt-4 max-sm:flex-col">
                        <flux:radio
                            value="pickup"
                            icon="shopping-bag"
                            :label="__('Pickup')"
                            :description="__('Collect from the counter at 214 Juniper Street.')"
                        />
                        <flux:radio
                            value="delivery"
                            icon="truck"
                            :label="__('Delivery')"
                            :description="__(':fee, free over :threshold. Within 5 km.', ['fee' => $this->formatPrice((int) config('ordering.delivery_fee_cents')), 'threshold' => $this->formatPrice((int) config('ordering.free_delivery_from_cents'))])"
                        />
                    </flux:radio.group>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        @if ($fulfilment === 'delivery')
                            <div class="sm:col-span-2">
                                <flux:input wire:model="address" :label="__('Delivery address')" :placeholder="__('Street, unit and postal code')" autocomplete="street-address" />
                            </div>
                        @endif

                        <flux:select wire:model="readyAt" :label="$fulfilment === 'delivery' ? __('Deliver at') : __('Ready for pickup at')">
                            @foreach ($this->readyTimes as $day => $times)
                                <optgroup label="{{ $day }}">
                                    @foreach ($times as $time)
                                        <option value="{{ $time->format('Y-m-d H:i') }}">{{ $day }}, {{ $time->format('g:i a') }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </flux:select>
                    </div>
                </section>

                {{-- Contact details --}}
                <section>
                    <h2 class="font-display text-2xl font-semibold">{{ __('Your details') }}</h2>

                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                        <flux:input wire:model="name" :label="__('Name')" autocomplete="name" />
                        <flux:input wire:model="phone" type="tel" :label="__('Phone')" autocomplete="tel" />
                        <div class="sm:col-span-2">
                            <flux:input wire:model="email" type="email" :label="__('Email')" autocomplete="email" :description:trailing="__('We send your order updates here.')" />
                        </div>
                        <div class="sm:col-span-2">
                            <flux:textarea wire:model="notes" rows="2" :label="__('Notes for the kitchen')" :placeholder="__('Allergies, a birthday message for the cake, where to leave it…')" />
                        </div>
                    </div>
                </section>
            </div>

            {{-- Summary --}}
            <aside class="rounded-3xl border border-stone-200/80 bg-white p-6 lg:sticky lg:top-6 dark:border-white/10 dark:bg-stone-900" data-test="order-summary">
                <h2 class="font-display text-xl font-semibold">{{ __('Summary') }}</h2>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-stone-600 dark:text-stone-400">{{ __('Subtotal') }}</dt>
                        <dd class="tabular-nums">{{ $this->formatPrice($this->subtotalCents) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-stone-600 dark:text-stone-400">{{ __('Delivery') }}</dt>
                        <dd class="tabular-nums">{{ $fulfilment === 'delivery' ? ($this->deliveryFeeCents === 0 ? __('Free') : $this->formatPrice($this->deliveryFeeCents)) : '—' }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-stone-200 pt-3 text-base font-semibold dark:border-white/10">
                        <dt>{{ __('Total') }}</dt>
                        <dd class="tabular-nums" data-test="order-total">{{ $this->formatPrice($this->subtotalCents + $this->deliveryFeeCents) }}</dd>
                    </div>
                </dl>

                <div class="mt-5 rounded-2xl bg-stone-50 p-3 text-xs text-stone-600 dark:bg-white/5 dark:text-stone-400">
                    <p class="flex items-center gap-2 font-semibold text-stone-800 dark:text-stone-200">
                        <flux:icon.banknotes variant="micro" />
                        {{ $fulfilment === 'delivery' ? __('Pay on delivery') : __('Pay at pickup') }}
                    </p>
                    <p class="mt-1">{{ __('Cash, debit or credit card. Online card payment is coming soon.') }}</p>
                </div>

                <flux:error name="cart" class="mt-4" />

                <flux:button type="submit" variant="primary" class="mt-5 w-full" data-test="place-order">
                    {{ __('Place order') }}
                </flux:button>
            </aside>
        </form>
    @endif
</main>
