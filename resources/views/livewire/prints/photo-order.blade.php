{{--
    The customer's phone flow: photos, print counts, details, done.

    One Livewire component, four steps, and the channel decides what the last
    two mean: an in-store order never shows a payment request (it ends at a
    pickup code), a remote order adds the mailing address and the demo
    checkout. The sticky summary keeps the deal visible while quantities are
    chosen, because the deal is what turns 1 print into 3.
--}}
@php
    $channel = $this->channel;
@endphp

<div class="flex flex-1 flex-col">
<flux:progress class="mb-8" :value="min(100, $step * 25)" />

@if ($step === 1)
    <section>
        <flux:heading size="xl" level="1">{{ __('Choose your photos') }}</flux:heading>
        <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">
            {{ __('Pick one to twenty photos from your phone. Nothing is sent until you finish.') }}
        </flux:text>

        <label class="mt-8 flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-teal-300 bg-teal-50/50 px-6 py-10 text-center transition hover:border-teal-400 hover:bg-teal-50 dark:border-teal-700 dark:bg-teal-950/30 dark:hover:border-teal-500">
            <flux:icon name="photo" variant="solid" class="size-10 text-teal-600 dark:text-teal-400" />
            <span class="text-base font-semibold text-teal-900 dark:text-teal-100">{{ __('Add photos') }}</span>
            <span class="text-sm text-teal-700/80 dark:text-teal-300/80">{{ __('JPEG, PNG or WebP') }}</span>
            <input type="file" class="hidden" accept="image/jpeg,image/png,image/webp" multiple wire:model="photos" />
        </label>

        <div wire:loading.flex class="mt-4 items-center gap-2 text-sm text-neutral-500">
            <flux:icon name="arrow-path" class="size-4 animate-spin" />
            {{ __('Uploading…') }}
        </div>

        <flux:error name="photos" />
        <flux:error name="photos.*" />

        @if (count($photos) > 0)
            <ul class="mt-6 grid grid-cols-3 gap-3 sm:grid-cols-4">
                @foreach ($photos as $index => $photo)
                    <li class="group relative aspect-square overflow-hidden rounded-xl bg-neutral-100 dark:bg-neutral-800">
                        <img
                            src="{{ $photo->temporaryUrl() }}"
                            alt="{{ __('Photo :number', ['number' => $index + 1]) }}"
                            class="size-full object-cover"
                        />
                        <button
                            type="button"
                            wire:click="removePhoto({{ $index }})"
                            wire:loading.attr="disabled"
                            class="absolute top-1.5 right-1.5 flex size-7 items-center justify-center rounded-full bg-neutral-900/70 text-white backdrop-blur transition hover:bg-red-600"
                            aria-label="{{ __('Remove photo :number', ['number' => $index + 1]) }}"
                        >
                            <flux:icon name="x-mark" class="size-4" />
                        </button>
                    </li>
                @endforeach
            </ul>

            <p class="mt-4 text-sm text-neutral-500">{{ __(':count photos selected', ['count' => count($photos)]) }}</p>
        @endif
    </section>
@elseif ($step === 2)
    <section>
        <flux:heading size="xl" level="1">{{ __('How many of each?') }}</flux:heading>
        <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">
            {{
                __('One print is :unit, or any three prints for :bundle.',
                    ['unit' => $this->quote()->unit(), 'bundle' => $this->quote()->bundle()])
            }}
        </flux:text>

        <ul class="mt-8 space-y-4">
            @foreach ($photos as $index => $photo)
                <li class="flex items-center gap-4 rounded-2xl border border-neutral-200 bg-white p-3 dark:border-neutral-800 dark:bg-neutral-900">
                    <img
                        src="{{ $photo->temporaryUrl() }}"
                        alt="{{ __('Photo :number', ['number' => $index + 1]) }}"
                        class="size-16 shrink-0 rounded-xl object-cover"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ __('Photo :number', ['number' => $index + 1]) }}</p>
                        <p class="text-sm text-neutral-500">
                            {{ __(':quantity × :unit', ['quantity' => (int) ($quantities[$index] ?? 1), 'unit' => $this->quote()->unit()]) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            wire:click="adjustQuantity({{ $index }}, -1)"
                            wire:loading.attr="disabled"
                            class="flex size-11 items-center justify-center rounded-xl border border-neutral-200 text-lg font-medium transition hover:border-teal-400 hover:text-teal-600 disabled:opacity-40 dark:border-neutral-700"
                            aria-label="{{ __('One fewer print of photo :number', ['number' => $index + 1]) }}"
                        >
                            &minus;
                        </button>
                        <span
                            class="w-9 text-center text-lg font-semibold tabular-nums"
                            aria-live="polite"
                        >{{ (int) ($quantities[$index] ?? 1) }}</span>
                        <button
                            type="button"
                            wire:click="adjustQuantity({{ $index }}, +1)"
                            wire:loading.attr="disabled"
                            class="flex size-11 items-center justify-center rounded-xl border border-neutral-200 text-lg font-medium transition hover:border-teal-400 hover:text-teal-600 disabled:opacity-40 dark:border-neutral-700"
                            aria-label="{{ __('One more print of photo :number', ['number' => $index + 1]) }}"
                        >
                            &plus;
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>

        <flux:error name="quantities" />
        <flux:error name="quantities.*" />

        <div class="mt-6">
            @if ($this->quote()->hasSavings())
                <flux:callout color="emerald" icon="sparkles" class="dark:border-transparent">
                    {{ __('3-print deal applied — you are saving :amount.', ['amount' => $this->quote()->savings()]) }}
                </flux:callout>
            @elseif ($this->quote()->printsToNextBundle() > 0)
                <flux:callout color="amber" icon="tag" class="dark:border-transparent">
                    {{ trans_choice('Add :prints more print and pay the bundle price.|Add :prints more prints and pay the bundle price.', $this->quote()->printsToNextBundle(), ['prints' => $this->quote()->printsToNextBundle()]) }}
                </flux:callout>
            @endif
        </div>
    </section>
@elseif ($step === 3)
    <section>
        <flux:heading size="xl" level="1">
            {{ $channel === App\Prints\Enums\PrintChannel::InStore ? __('Almost there') : __('Where should we send them?') }}
        </flux:heading>
        <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">
            {{ __('We only need enough to hand your prints to you.') }}
        </flux:text>

        <div class="mt-8 space-y-5">
            <flux:field>
                <flux:label>{{ __('Your name') }}</flux:label>
                <flux:input wire:model="customerName" placeholder="Jordan Reyes" autocomplete="name" />
                <flux:error name="customerName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Phone (optional)') }}</flux:label>
                <flux:input wire:model="customerPhone" type="tel" placeholder="+1 (555) 010-2233" autocomplete="tel" />
                <flux:error name="customerPhone" />
            </flux:field>

            @if ($channel === App\Prints\Enums\PrintChannel::Remote)
                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input
                        wire:model="customerEmail"
                        type="email"
                        placeholder="you@example.com"
                        autocomplete="email"
                    />
                    <flux:error name="customerEmail" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Mailing address') }}</flux:label>
                    <flux:textarea
                        wire:model="mailingAddress"
                        rows="3"
                        placeholder="14 Kingfisher Lane&#10;Rockport, TX 78382"
                        autocomplete="street-address"
                    />
                    <flux:error name="mailingAddress" />
                </flux:field>

                <div class="rounded-2xl border border-neutral-200 bg-neutral-50 p-5 dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="flex items-center gap-2">
                        <flux:icon name="credit-card" class="size-5 text-teal-600 dark:text-teal-400" />
                        <p class="font-semibold">{{ __('Checkout') }}</p>
                        <flux:badge size="sm" color="amber" inset="top bottom">Demo</flux:badge>
                    </div>
                    <p class="mt-1 mb-4 text-sm text-neutral-500">
                        {{ __('Any card details succeed — this demo takes no real payment.') }}
                    </p>
                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>{{ __('Card number') }}</flux:label>
                            <flux:input
                                wire:model="cardNumber"
                                inputmode="numeric"
                                autocomplete="cc-number"
                                placeholder="4242 4242 4242 4242"
                            />
                            <flux:error name="cardNumber" />
                        </flux:field>
                        <div class="grid grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>{{ __('Expiry') }}</flux:label>
                                <flux:input wire:model="cardExpiry" placeholder="12/28" autocomplete="cc-exp" />
                                <flux:error name="cardExpiry" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('CVC') }}</flux:label>
                                <flux:input
                                    wire:model="cardCvc"
                                    inputmode="numeric"
                                    placeholder="123"
                                    autocomplete="cc-csc"
                                />
                                <flux:error name="cardCvc" />
                            </flux:field>
                        </div>
                    </div>
                </div>
            @else
                <flux:callout color="teal" icon="banknotes" class="dark:border-transparent">
                    {{ __('No payment now. Pick your prints up at the counter and pay there — they will be ready in minutes.') }}
                    @isset($counter?->address)
                        <p class="mt-1 text-sm opacity-80">{{ $counter->address }}</p>
                    @endisset
                </flux:callout>
            @endif
        </div>
    </section>
@elseif ($step === 4 && $order !== null)
    <section class="text-center">
        <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950">
            <flux:icon name="check-circle" variant="solid" class="size-9 text-emerald-600 dark:text-emerald-400" />
        </div>

        <flux:heading size="xl" level="1" class="mt-6">{{ __('Your prints are on their way') }}</flux:heading>

        <div class="mt-6 rounded-2xl border-2 border-dashed border-teal-300 bg-teal-50/60 px-6 py-5 dark:border-teal-700 dark:bg-teal-950/30">
            <flux:text
                size="sm"
                class="text-teal-700 dark:text-teal-300"
            >{{ __('Show this code at the counter') }}</flux:text>
            <p class="mt-1 font-mono text-4xl font-bold tracking-widest text-teal-900 dark:text-teal-100">
                {{ $order->code }}
            </p>
        </div>

        <flux:text class="mt-6 text-neutral-600 dark:text-neutral-400">
            @if ($order->channel === App\Prints\Enums\PrintChannel::InStore)
                {{ __('We have your photos. Our team is printing them now — collect and pay at the counter.') }}
            @else
                {{ __('We have your payment and your photos. Your prints will be posted to:') }}
                <span class="block font-medium whitespace-pre-line text-neutral-900 dark:text-neutral-100">{{ $order->mailing_address }}</span>
            @endif
        </flux:text>

        <ul class="mt-6 grid grid-cols-3 gap-3 text-left">
            @foreach ($order->items as $item)
                @php
                    $thumb = $item->media?->url('thumb') ?? $item->media?->url();
                @endphp
                <li class="relative aspect-square overflow-hidden rounded-xl bg-neutral-100 dark:bg-neutral-800">
                    @if ($thumb)
                        <img
                            src="{{ $thumb }}"
                            alt="{{ __('Photo :number', ['number' => $loop->iteration]) }}"
                            class="size-full object-cover"
                        />
                    @else
                        <div class="flex size-full items-center justify-center text-neutral-400">
                            <flux:icon name="photo" class="size-6" />
                        </div>
                    @endif
                    <span class="absolute right-1.5 bottom-1.5 rounded-full bg-neutral-900/75 px-2 py-0.5 text-xs font-semibold text-white tabular-nums">
                        &times;{{ $item->quantity }}
                    </span>
                </li>
            @endforeach
        </ul>
    </section>
@endif

{{--
    The summary bar: what the selection costs, and the way forward. Fixed on
    phones (a thumb reaches it), static beside the card on larger screens.
--}}
<div class="fixed inset-x-0 bottom-0 z-10 border-t border-neutral-200 bg-white/90 backdrop-blur sm:static sm:mt-10 sm:rounded-2xl sm:border dark:border-neutral-800 dark:bg-neutral-950/90">
    <div class="mx-auto flex w-full max-w-xl items-center justify-between gap-4 px-5 py-4 sm:px-6">
        @if ($step < 4)
            <div>
                @if ($step >= 2)
                    <p class="text-lg font-semibold">
                        {{ $this->quote()->total() }}
                        @if ($this->quote()->hasSavings())
                            <s class="text-sm font-normal text-neutral-400">{{ $this->quote()->listTotal() }}</s>
                        @endif
                    </p>
                    <p class="text-sm text-neutral-500">
                        {{ trans_choice(':prints print|:prints prints', $this->quote()->prints, ['prints' => $this->quote()->prints]) }}
                    </p>
                @else
                    <p class="text-sm text-neutral-500">
                        {{ __('Any :size prints for :bundle', ['size' => App\Prints\PrintPricing::BUNDLE_SIZE, 'bundle' => $this->quote()->bundle()]) }}
                    </p>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if ($step === 2)
                    <flux:button variant="ghost" wire:click="$set('step', 1)" size="sm">{{ __('Back') }}</flux:button>
                @endif
                @if ($step === 3)
                    <flux:button variant="ghost" wire:click="$set('step', 2)" size="sm">{{ __('Back') }}</flux:button>
                @endif

                @if ($step === 1)
                    <flux:button
                        variant="primary"
                       
                        wire:click="chooseQuantities"
                        wire:loading.attr="disabled"
                        icon-trailing="arrow-right"
                    >
                        {{ __('Next') }}
                    </flux:button>
                @elseif ($step === 2)
                    <flux:button
                        variant="primary"
                       
                        wire:click="provideDetails"
                        wire:loading.attr="disabled"
                        icon-trailing="arrow-right"
                    >
                        {{ __('Next') }}
                    </flux:button>
                @elseif ($step === 3)
                    <flux:button
                        variant="primary"
                       
                        wire:click="placeOrder"
                        wire:loading.attr="disabled"
                        wire:target="placeOrder"
                        icon="paper-airplane"
                    >
                        {{
                            $channel === App\Prints\Enums\PrintChannel::Remote
                            ? __('Pay :amount and send', ['amount' => $this->quote()->total()])
                            : __('Send to the counter')
                        }}
                    </flux:button>
                @endif
            </div>
        @else
            <flux:button variant="primary" wire:click="startAnother" icon="plus">
                {{ __('Send more photos') }}
            </flux:button>
        @endif
    </div>
</div>
</div>
