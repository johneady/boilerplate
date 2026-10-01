@php
    $tour = $this->tour;
    $quote = $this->quote;
@endphp

<div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-lg dark:border-neutral-800 dark:bg-neutral-900">
    @if ($sentReference !== null)
        <div class="p-6 text-center" data-test="inquiry-sent">
            <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-teal-50 text-teal-700 dark:bg-teal-400/10 dark:text-teal-300">
                <flux:icon.check-badge class="size-8" />
            </span>
            <h2 class="mt-4 font-display text-2xl font-semibold">{{ __('Request received!') }}</h2>
            <p class="mt-2 text-neutral-600 dark:text-neutral-400">
                {{ __('Your reference is') }}
                <span class="block font-mono text-xl font-semibold text-neutral-900 dark:text-white">{{ $sentReference }}</span>
            </p>
            <p class="mt-4 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('We\'ve emailed you a copy. A trip specialist will confirm availability and reply within one business day. Nothing is charged until you approve the final quote.') }}
            </p>
            @auth
                <flux:button class="mt-6" :href="route('dashboard')" variant="primary" wire:navigate>{{ __('View my trips') }}</flux:button>
            @else
                <flux:button class="mt-6" :href="route('tours.index')" wire:navigate>{{ __('Keep browsing') }}</flux:button>
            @endauth
        </div>
    @else
        <div class="bg-teal-900 px-6 py-5 text-white">
            @if ($tour !== null)
                <p class="text-sm text-white/80">{{ __('From') }}</p>
                <p class="text-3xl font-semibold">{{ $tour->formattedPrice() }} <span class="text-base font-normal text-white/80">{{ __('per person') }}</span></p>
            @else
                <h2 class="font-display text-2xl font-semibold">{{ __('Tell us about your trip') }}</h2>
                <p class="mt-1 text-sm text-white/80">{{ __('Free itinerary and price within two business days.') }}</p>
            @endif
        </div>

        <form wire:submit="submit" class="space-y-5 p-6">
            @if ($tour !== null)
                @if ($this->departures->isNotEmpty())
                    <flux:select wire:model.live="departureId" :label="__('Departure date')">
                        @foreach ($this->departures as $departure)
                            {{-- The label is built in one expression: Livewire wraps a Blade
                                 @if in comment markers, and browsers drop comments inside an
                                 <option>, which breaks the next morph. --}}
                            <flux:select.option :value="$departure->id">{{ $departure->dateRange().' · '.$departure->formattedPrice().($departure->isNearlyFull() ? ' ('.trans_choice(':count seat left|:count seats left', $departure->seatsLeft()).')' : '') }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @else
                    <flux:callout icon="calendar" variant="secondary" :text="__('No dates are open right now. Send a request and we\'ll hold your place on the next one.')" />
                @endif
            @else
                <flux:select wire:model="destinationId" :label="__('Where would you like to go?')">
                    <flux:select.option value="">{{ __('Not sure yet: inspire me') }}</flux:select.option>
                    @foreach ($this->destinations as $destination)
                        <flux:select.option :value="$destination->id">{{ $destination->place() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="travelMonth" :label="__('When?')">
                    <flux:select.option value="">{{ __('I\'m flexible') }}</flux:select.option>
                    @foreach ($this->months as $month)
                        <flux:select.option :value="$month">{{ $month }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model.live.debounce.250ms="adults" type="number" min="1" :max="config('travel.max_party_size')" :label="__('Adults')" />
                <flux:input
                    wire:model.live.debounce.250ms="childCount"
                    type="number"
                    min="0"
                    :max="config('travel.max_party_size') - 1"
                    :label="__('Children under :age', ['age' => config('travel.child_max_age') + 1])"
                />
            </div>

            @if ($quote !== null)
                <div class="rounded-xl bg-stone-50 p-4 text-sm dark:bg-neutral-800" data-test="quote">
                    <dl class="space-y-1.5">
                        <div class="flex justify-between gap-3">
                            <dt>{{ trans_choice(':count adult|:count adults', $quote->adults) }} × {{ \App\Travel\Price::format($quote->adultPriceCents) }}</dt>
                            <dd>{{ \App\Travel\Price::format($quote->adultsTotalCents()) }}</dd>
                        </div>
                        @if ($quote->children > 0)
                            <div class="flex justify-between gap-3">
                                <dt>{{ trans_choice(':count child|:count children', $quote->children) }} × {{ \App\Travel\Price::format($quote->childPriceCents) }}</dt>
                                <dd>{{ \App\Travel\Price::format($quote->childrenTotalCents()) }}</dd>
                            </div>
                        @endif
                        @if ($quote->singleSupplementCents > 0)
                            <div class="flex justify-between gap-3">
                                <dt>{{ __('Solo traveller supplement') }}</dt>
                                <dd>{{ \App\Travel\Price::format($quote->singleSupplementCents) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-3 border-t border-neutral-200 pt-2 text-base font-semibold dark:border-neutral-700">
                            <dt>{{ __('Estimated total') }}</dt>
                            <dd data-test="quote-total">{{ \App\Travel\Price::format($quote->totalCents()) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 text-neutral-600 dark:text-neutral-400">
                            <dt>{{ __(':percent% deposit to secure seats', ['percent' => config('travel.deposit_percent')]) }}</dt>
                            <dd>{{ \App\Travel\Price::format($quote->depositCents()) }}</dd>
                        </div>
                    </dl>
                </div>
            @endif

            <flux:error name="departureId" />

            <flux:input wire:model="name" :label="__('Full name')" autocomplete="name" required />
            <flux:input wire:model="email" type="email" :label="__('Email')" autocomplete="email" required />
            <flux:input wire:model="phone" type="tel" :label="__('Phone (optional)')" autocomplete="tel" />
            <flux:textarea
                wire:model="message"
                rows="3"
                :label="$tour !== null ? __('Anything we should know?') : __('Your dream trip')"
                :placeholder="$tour !== null ? __('Dietary needs, room preferences, celebrations...') : __('Who\'s travelling, what you love, budget per person, must-sees...')"
            />

            <div hidden aria-hidden="true">
                <label for="trip-website">{{ __('Leave this field empty') }}</label>
                <input id="trip-website" type="text" wire:model="website" tabindex="-1" autocomplete="off" />
            </div>

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-full bg-orange-500 px-6 py-3 text-base font-semibold text-white transition hover:bg-orange-600 disabled:opacity-60"
                wire:loading.attr="disabled"
                wire:target="submit"
            >
                <span wire:loading.remove wire:target="submit">{{ $tour !== null ? __('Request to book') : __('Send my trip request') }}</span>
                <span wire:loading wire:target="submit">{{ __('Sending...') }}</span>
            </button>

            <p class="text-center text-xs text-neutral-500">
                {{ __('No payment now. We confirm availability first, usually within one business day.') }}
            </p>
        </form>
    @endif
</div>
