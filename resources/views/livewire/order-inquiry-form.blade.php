@php
    $settings = app(\App\Settings\Settings::class);
    $items = $this->menuItems;
    $grouped = $items->groupBy(fn (\App\Models\MenuItem $item): string => $item->category->value);
    $estimate = \App\Bakery\Money::format($this->estimateCents);
@endphp

{{--
    The order inquiry form. The layout comes from the component's render(), as
    for the contact form.

    The estimate and the earliest date are computed properties, so they follow
    the lines as the customer edits them (wire:model.live on the item and
    quantity fields only -- the rest of the form sends nothing until submit).
--}}
<main class="flex-1 py-8 sm:py-12">
    @if ($reference !== '')
        <div
            class="mx-auto max-w-2xl rounded-3xl border border-amber-200 bg-white/80 p-8 text-center shadow-sm sm:p-12 dark:border-white/10 dark:bg-stone-900/70"
            data-test="order-sent"
        >
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                <flux:icon.check-badge class="size-9" />
            </div>
            <h1 class="font-display mt-6 text-4xl font-semibold tracking-tight">
                {{ __('Your order request is in!') }}
            </h1>
            <p class="mt-4 text-lg text-stone-600 dark:text-stone-400">
                {{ __('Your reference is') }}
                <span class="rounded-lg bg-amber-100 px-2 py-0.5 font-mono font-semibold text-amber-900 dark:bg-amber-500/15 dark:text-amber-200">{{ $reference }}</span>
            </p>
            <p class="mx-auto mt-4 max-w-md leading-relaxed text-stone-600 dark:text-stone-400">
                {{ __('We have emailed you a copy. We will check the date against our baking calendar and reply within one day with the final price and how to pay. Nothing is baked until you confirm.') }}
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a
                    href="{{ route('home') }}#menu"
                    class="rounded-full border border-stone-300 px-5 py-2.5 font-semibold hover:bg-white dark:border-white/15 dark:hover:bg-white/5"
                    wire:navigate
                >
                    {{ __('Back to the menu') }}
                </a>
                <button
                    type="button"
                    wire:click="startAnother"
                    class="rounded-full bg-amber-700 px-5 py-2.5 font-semibold text-white hover:bg-amber-800 dark:bg-amber-500 dark:text-stone-950"
                >
                    {{ __('Send another request') }}
                </button>
            </div>
        </div>
    @else
        <div class="max-w-2xl">
            <h1 class="font-display text-4xl font-semibold tracking-tight sm:text-5xl">{{ __('Request an order') }}</h1>
            <p class="mt-4 text-lg leading-relaxed text-stone-600 dark:text-stone-400">
                {{ __('Tell us what you would like and when. This is a request, not a payment: we confirm the date and final price by email within one day.') }}
            </p>
        </div>

        <form wire:submit="submit" class="mt-10 grid gap-8 lg:grid-cols-[1fr_20rem]">
            <div class="space-y-8">
                {{-- 1. Items --}}
                <section class="rounded-3xl border border-amber-200/70 bg-white/80 p-6 sm:p-8 dark:border-white/10 dark:bg-stone-900/60">
                    <h2 class="font-display flex items-center gap-3 text-2xl font-semibold">
                        <span class="flex size-8 items-center justify-center rounded-full bg-amber-700 font-sans text-sm text-white dark:bg-amber-500 dark:text-stone-950">1</span>
                        {{ __('What would you like?') }}
                    </h2>

                    <div class="mt-6 space-y-4">
                        @foreach ($lines as $index => $line)
                            @php($chosen = $items->firstWhere('id', (int) $line['menuItemId']))
                            <div
                                class="flex items-start gap-3 rounded-2xl border border-stone-200 bg-stone-50/60 p-3 dark:border-white/10 dark:bg-white/5"
                                wire:key="line-{{ $index }}"
                            >
                                <div class="hidden size-16 shrink-0 overflow-hidden rounded-xl bg-amber-100 sm:block dark:bg-stone-800">
                                    @if ($chosen?->imageUrl())
                                        <img src="{{ $chosen->imageUrl() }}" alt="" class="size-full object-cover" />
                                    @else
                                        <div class="flex size-full items-center justify-center text-amber-600">
                                            <flux:icon.cake />
                                        </div>
                                    @endif
                                </div>

                                <div class="grid flex-1 gap-3 sm:grid-cols-[1fr_6.5rem]">
                                    <flux:select
                                        wire:model.live="lines.{{ $index }}.menuItemId"
                                        :label="__('Item')"
                                        :placeholder="__('Choose from the menu…')"
                                    >
                                        @foreach ($grouped as $category => $categoryItems)
                                            <optgroup label="{{ __(\App\Bakery\MenuCategory::from($category)->label()) }}">
                                                @foreach ($categoryItems as $item)
                                                    <option value="{{ $item->id }}">
                                                        {{ $item->name }} — {{ $item->formattedPrice() }} {{ $item->price_unit }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </flux:select>

                                    <flux:input
                                        wire:model.live.debounce.300ms="lines.{{ $index }}.quantity"
                                        :label="__('Quantity')"
                                        type="number"
                                        min="1"
                                        max="{{ config('bakery.max_quantity') }}"
                                    />
                                </div>

                                @if (count($lines) > 1)
                                    <flux:button
                                        wire:click="removeLine({{ $index }})"
                                        variant="ghost"
                                        size="sm"
                                        icon="x-mark"
                                        class="mt-6"
                                        :aria-label="__('Remove this item')"
                                    />
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if (count($lines) < (int) config('bakery.max_lines'))
                        <flux:button wire:click="addLine" variant="ghost" icon="plus" class="mt-4">
                            {{ __('Add another item') }}
                        </flux:button>
                    @endif
                </section>

                {{-- 2. When --}}
                <section class="rounded-3xl border border-amber-200/70 bg-white/80 p-6 sm:p-8 dark:border-white/10 dark:bg-stone-900/60">
                    <h2 class="font-display flex items-center gap-3 text-2xl font-semibold">
                        <span class="flex size-8 items-center justify-center rounded-full bg-amber-700 font-sans text-sm text-white dark:bg-amber-500 dark:text-stone-950">2</span>
                        {{ __('When and how?') }}
                    </h2>

                    <div class="mt-6 space-y-6">
                        <flux:input
                            wire:model="neededOn"
                            :label="__('Date needed')"
                            type="date"
                            min="{{ $this->earliestDate->toDateString() }}"
                            max="{{ $this->latestDate->toDateString() }}"
                            required
                            :description="__('This order needs :days days\' notice, so the earliest date is :date.', ['days' => $this->noticeDays, 'date' => $settings->formatDate($this->earliestDate)])"
                        />

                        <flux:radio.group
                            wire:model.live="fulfilment"
                            :label="__('Pickup or delivery')"
                            variant="cards"
                            class="max-sm:flex-col"
                        >
                            @foreach ($fulfilmentOptions as $option)
                                <flux:radio
                                    :value="$option->value"
                                    :label="__($option->label())"
                                    :description="$option === \App\Bakery\Fulfilment::Pickup
                                        ? __('Collect from our home kitchen. Address sent on confirmation.')
                                        : __('Within 15 km, delivery fee confirmed with your quote.')"
                                />
                            @endforeach
                        </flux:radio.group>

                        @if ($fulfilment === \App\Bakery\Fulfilment::Delivery->value)
                            <flux:textarea
                                wire:model="deliveryAddress"
                                :label="__('Delivery address')"
                                rows="2"
                                required
                            />
                        @endif
                    </div>
                </section>

                {{-- 3. Details --}}
                <section class="rounded-3xl border border-amber-200/70 bg-white/80 p-6 sm:p-8 dark:border-white/10 dark:bg-stone-900/60">
                    <h2 class="font-display flex items-center gap-3 text-2xl font-semibold">
                        <span class="flex size-8 items-center justify-center rounded-full bg-amber-700 font-sans text-sm text-white dark:bg-amber-500 dark:text-stone-950">3</span>
                        {{ __('Tell us more') }}
                    </h2>

                    <div class="mt-6 space-y-6">
                        <flux:select wire:model="occasion" :label="__('Occasion')" :placeholder="__('Optional')">
                            @foreach ($occasionOptions as $option)
                                <flux:select.option :value="$option->value">
                                    {{ __($option->label()) }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:textarea
                            wire:model="details"
                            :label="__('Details')"
                            rows="4"
                            :placeholder="__('Cake message, colours, flavours, number of guests, anything we should know.')"
                        />

                        <flux:textarea
                            wire:model="allergies"
                            :label="__('Allergies or dietary needs')"
                            rows="2"
                            :description="__('Our kitchen handles nuts, wheat, dairy and eggs. Tell us and we will talk it through.')"
                        />
                    </div>
                </section>

                {{-- 4. Contact --}}
                <section class="rounded-3xl border border-amber-200/70 bg-white/80 p-6 sm:p-8 dark:border-white/10 dark:bg-stone-900/60">
                    <h2 class="font-display flex items-center gap-3 text-2xl font-semibold">
                        <span class="flex size-8 items-center justify-center rounded-full bg-amber-700 font-sans text-sm text-white dark:bg-amber-500 dark:text-stone-950">4</span>
                        {{ __('Your details') }}
                    </h2>

                    <div class="mt-6 grid gap-6 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <flux:input wire:model="name" :label="__('Your name')" autocomplete="name" required />
                        </div>
                        <flux:input
                            wire:model="email"
                            :label="__('Email')"
                            type="email"
                            autocomplete="email"
                            required
                            :description="__('We send the confirmation here.')"
                        />
                        <flux:input
                            wire:model="phone"
                            :label="__('Phone')"
                            type="tel"
                            autocomplete="tel"
                            :description="__('Optional, for pickup day.')"
                        />
                    </div>

                    {{-- The honeypot. See livewire/contact.blade.php. --}}
                    <div hidden aria-hidden="true">
                        <label for="order-website">{{ __('Leave this field empty') }}</label>
                        <input id="order-website" type="text" wire:model="website" tabindex="-1" autocomplete="off" />
                    </div>
                </section>
            </div>

            {{-- Summary --}}
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-3xl bg-stone-900 p-6 text-stone-100 shadow-xl shadow-amber-900/10 dark:bg-stone-900 dark:ring-1 dark:ring-white/10">
                    <h2 class="font-display text-xl font-semibold">{{ __('Your order') }}</h2>

                    <ul class="mt-4 space-y-2 text-sm">
                        @forelse (collect($lines)->filter(fn ($line) => $items->firstWhere('id', (int) $line['menuItemId'])) as $line)
                            @php($chosen = $items->firstWhere('id', (int) $line['menuItemId']))
                            <li class="flex justify-between gap-3">
                                <span>{{ max(1, (int) $line['quantity']) }} × {{ $chosen->name }}</span>
                                <span class="shrink-0 text-stone-400">{{ \App\Bakery\Money::format($chosen->price_cents * max(0, min((int) $line['quantity'], (int) config('bakery.max_quantity')))) }}</span>
                            </li>
                        @empty
                            <li class="text-stone-400">{{ __('Nothing chosen yet.') }}</li>
                        @endforelse
                    </ul>

                    <div class="mt-5 flex items-baseline justify-between border-t border-white/10 pt-4">
                        <span class="text-sm text-stone-400">{{ __('Menu estimate') }}</span>
                        <span
                            class="text-2xl font-semibold text-amber-300"
                            data-test="order-estimate"
                        >{{ $estimate }}</span>
                    </div>
                    <p class="mt-2 text-xs leading-relaxed text-stone-400">
                        {{ __('Final price confirmed by email. Custom decoration and delivery may change it.') }}
                    </p>

                    <button
                        type="submit"
                        class="mt-6 flex w-full items-center justify-center gap-2 rounded-full bg-amber-400 px-5 py-3 font-semibold text-stone-950 hover:bg-amber-300 disabled:opacity-60"
                        wire:loading.attr="disabled"
                        wire:target="submit"
                    >
                        <span wire:loading.remove wire:target="submit">{{ __('Send order request') }}</span>
                        <span wire:loading wire:target="submit">{{ __('Sending…') }}</span>
                    </button>

                    <p class="mt-4 flex items-start gap-2 text-xs text-stone-400">
                        <flux:icon.clock variant="micro" class="mt-0.5 shrink-0" />
                        {{ __('Earliest date for this order: :date', ['date' => $settings->formatDate($this->earliestDate)]) }}
                    </p>
                </div>
            </aside>
        </form>
    @endif
</main>
