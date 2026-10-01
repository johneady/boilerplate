<x-layouts::public :title="$tour->name" :description="$tour->summary">
    <main class="flex flex-1 flex-col">
        {{-- Hero --}}
        <section class="relative isolate overflow-hidden bg-neutral-900">
            @if ($tour->imageUrl() !== null)
                <img src="{{ $tour->imageUrl() }}" alt="" class="absolute inset-0 -z-10 size-full object-cover" fetchpriority="high" />
            @endif
            <div class="absolute inset-0 -z-10 bg-linear-to-t from-black/85 via-black/40 to-black/10"></div>

            <div class="mx-auto max-w-7xl px-4 pt-28 pb-10 text-white sm:px-6 sm:pt-44 lg:px-8">
                <nav aria-label="{{ __('Breadcrumb') }}" class="flex flex-wrap gap-x-1.5 text-sm text-white/80">
                    <a href="{{ route('tours.index') }}" class="hover:text-white" wire:navigate>{{ __('Tours') }}</a>
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('destinations.show', $tour->destination) }}" class="hover:text-white" wire:navigate>{{ $tour->destination->name }}</a>
                </nav>
                <h1 class="mt-3 max-w-3xl font-display text-4xl leading-tight font-semibold text-balance sm:text-6xl">{{ $tour->name }}</h1>
                <p class="mt-3 max-w-2xl text-lg text-white/85">{{ $tour->summary }}</p>

                <dl class="mt-8 flex flex-wrap gap-3 text-sm">
                    @foreach ([
                        ['icon' => 'clock', 'label' => __('Duration'), 'value' => $tour->durationLabel()],
                        ['icon' => 'user-group', 'label' => __('Group size'), 'value' => __('Max :count travellers', ['count' => $tour->group_size_max])],
                        ['icon' => $tour->style->icon(), 'label' => __('Style'), 'value' => __($tour->style->label())],
                        ['icon' => 'map-pin', 'label' => __('Destination'), 'value' => $tour->destination->place()],
                    ] as $fact)
                        <div class="flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 ring-1 ring-white/20 backdrop-blur">
                            <flux:icon :icon="$fact['icon']" variant="micro" />
                            <dt class="sr-only">{{ $fact['label'] }}</dt>
                            <dd class="font-medium">{{ $fact['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        <div class="mx-auto grid w-full max-w-7xl gap-10 px-4 py-10 sm:px-6 sm:py-14 lg:grid-cols-[1fr_24rem] lg:px-8">
            <div class="min-w-0 space-y-12">
                {{-- Overview --}}
                <section>
                    <h2 class="font-display text-2xl font-semibold">{{ __('Overview') }}</h2>
                    <div class="mt-4 space-y-4 leading-relaxed text-neutral-700 dark:text-neutral-300">
                        @foreach (preg_split('/\R{2,}/', trim($tour->description)) as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>

                    @if (filled($tour->highlights))
                        <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                            @foreach ($tour->highlights as $highlight)
                                <li class="flex gap-2.5">
                                    <flux:icon.sparkles variant="mini" class="mt-0.5 shrink-0 text-orange-500" />
                                    <span>{{ $highlight }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                {{-- Itinerary --}}
                @if (filled($tour->itinerary))
                    <section>
                        <h2 class="font-display text-2xl font-semibold">{{ __('Day by day') }}</h2>
                        <ol class="mt-6 space-y-0">
                            @foreach ($tour->itinerary as $index => $day)
                                <li class="relative flex gap-4 pb-8 last:pb-0" x-data="{ open: {{ $index < 2 ? 'true' : 'false' }} }">
                                    @unless ($loop->last)
                                        <span class="absolute top-10 left-5 h-[calc(100%-2.5rem)] w-px bg-teal-200 dark:bg-teal-900" aria-hidden="true"></span>
                                    @endunless
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-teal-700 text-xs font-semibold text-white">{{ $day['day'] ?? $index + 1 }}</span>
                                    <div class="min-w-0 flex-1 pt-1.5">
                                        <button type="button" class="flex w-full items-start justify-between gap-3 text-left" x-on:click="open = ! open" x-bind:aria-expanded="open">
                                            <span class="font-semibold">{{ trans_choice('Day :day|Days :day', str_contains((string) ($day['day'] ?? ''), '–') ? 2 : 1, ['day' => $day['day'] ?? $index + 1]) }}: {{ $day['title'] }}</span>
                                            <flux:icon.chevron-down variant="micro" class="mt-1 shrink-0 transition" x-bind:class="open && 'rotate-180'" />
                                        </button>
                                        <p class="mt-2 leading-relaxed text-neutral-600 dark:text-neutral-400" x-show="open" x-collapse>{{ $day['body'] }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                {{-- Inclusions --}}
                @if (filled($tour->inclusions))
                    <section class="rounded-2xl bg-stone-50 p-6 dark:bg-neutral-900">
                        <h2 class="font-display text-2xl font-semibold">{{ __('What\'s included') }}</h2>
                        <ul class="mt-4 grid gap-2.5 sm:grid-cols-2">
                            @foreach ($tour->inclusions as $inclusion)
                                <li class="flex gap-2.5">
                                    <flux:icon.check variant="mini" class="mt-0.5 shrink-0 text-teal-700 dark:text-teal-400" />
                                    <span>{{ $inclusion }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-4 text-sm text-neutral-500">{{ __('Not included: international flights, travel insurance, visas and personal spending. We can arrange all of these for you.') }}</p>
                    </section>
                @endif

                {{-- Dates and prices --}}
                <section id="dates" x-data>
                    <h2 class="font-display text-2xl font-semibold">{{ __('Dates & prices') }}</h2>
                    <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('Per person, twin share. Solo travellers add :supplement for a private room.', ['supplement' => \App\Travel\Price::format($tour->single_supplement_cents)]) }}
                    </p>

                    @if ($departures->isEmpty())
                        <p class="mt-6 rounded-xl border border-dashed border-neutral-300 p-6 text-neutral-600 dark:border-neutral-700 dark:text-neutral-400">
                            {{ __('No upcoming departures are scheduled yet. Send a request below and we\'ll tell you first when dates open.') }}
                        </p>
                    @else
                        <ul class="mt-6 divide-y divide-neutral-200 overflow-hidden rounded-2xl border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
                            @foreach ($departures as $departure)
                                <li class="flex flex-wrap items-center gap-x-6 gap-y-2 px-5 py-4" data-test="departure-row">
                                    <div class="min-w-48 flex-1">
                                        <p class="font-semibold">{{ $departure->dateRange() }}</p>
                                        <p class="text-sm">
                                            @if ($departure->isSoldOut())
                                                <span class="text-neutral-500">{{ __('Sold out') }}</span>
                                            @elseif ($departure->isNearlyFull())
                                                <span class="font-medium text-orange-600 dark:text-orange-400">{{ trans_choice('Only :count seat left|Only :count seats left', $departure->seatsLeft()) }}</span>
                                            @else
                                                <span class="text-teal-700 dark:text-teal-400">{{ __('Available') }}</span>
                                            @endif
                                        </p>
                                    </div>
                                    <p class="text-lg font-semibold">
                                        {{ $departure->formattedPrice() }}
                                        @if ($departure->price_per_person_cents !== null && $departure->price_per_person_cents < $tour->price_per_person_cents)
                                            <span class="ml-1 text-sm font-normal text-neutral-500 line-through">{{ $tour->formattedPrice() }}</span>
                                        @endif
                                    </p>
                                    @if ($departure->isSoldOut())
                                        <flux:button size="sm" disabled>{{ __('Sold out') }}</flux:button>
                                    @else
                                        <flux:button
                                            size="sm"
                                            variant="primary"
                                            x-on:click="Livewire.dispatch('select-departure', { id: {{ $departure->id }} }); document.getElementById('book').scrollIntoView({ behavior: 'smooth' })"
                                        >
                                            {{ __('Select') }}
                                        </flux:button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                @if ($tour->imageCredit())
                    <p class="text-xs text-neutral-500">{{ $tour->imageCredit() }}</p>
                @endif
            </div>

            {{-- Booking request --}}
            <aside id="book" class="scroll-mt-24 lg:sticky lg:top-24 lg:self-start">
                <livewire:travel.trip-inquiry-form :tour="$tour" :departure="$selectedDeparture" />
            </aside>
        </div>

        @if ($relatedTours->isNotEmpty())
            <section class="bg-stone-50 py-12 sm:py-16 dark:bg-neutral-900/50">
                <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                    <h2 class="font-display text-2xl font-semibold">{{ __('You may also like') }}</h2>
                    <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($relatedTours as $related)
                            <x-travel.tour-card :tour="$related" />
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- Room for the sticky bar below, so it never covers the footer. --}}
        <div class="h-16 lg:hidden" aria-hidden="true"></div>

        {{-- Sticky mobile call to action --}}
        <div class="fixed inset-x-0 bottom-0 z-30 flex items-center justify-between gap-4 border-t border-neutral-200 bg-white/95 px-4 py-3 backdrop-blur lg:hidden dark:border-neutral-800 dark:bg-neutral-950/95">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('From') }} <span class="text-lg font-semibold text-neutral-900 dark:text-white">{{ $tour->formattedPrice() }}</span>
            </p>
            <a href="#book" class="rounded-full bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white">{{ __('Request to book') }}</a>
        </div>
    </main>
</x-layouts::public>
