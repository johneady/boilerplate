{{--
    The home page. No :title is passed on purpose: the home page takes the
    site-wide SEO title rather than prefixing it with a page name.

    The search is a plain GET form onto the tour finder, whose filters live in
    the URL -- so it works before any JavaScript has loaded.
--}}
@php
    $heroDestination = $destinations->firstWhere('slug', 'santorini') ?? $destinations->first();

    $services = [
        ['icon' => 'user-group', 'title' => __('Small groups, never crowds'), 'body' => __('Twelve travellers at most, so you get into the family-run tavernas and quiet trailheads big coaches can\'t.')],
        ['icon' => 'map', 'title' => __('Local guides, start to finish'), 'body' => __('Every tour is led by a guide who lives there, and backed by our own team on call 24/7 while you travel.')],
        ['icon' => 'paper-airplane', 'title' => __('Flights & transfers arranged'), 'body' => __('Add international flights, airport pick-ups and extra hotel nights. We book it all on one itinerary.')],
        ['icon' => 'shield-check', 'title' => __('Travel insurance & visas'), 'body' => __('We help you choose cover and walk you through visa and entry paperwork for every destination we sell.')],
        ['icon' => 'pencil-square', 'title' => __('Tailor-made journeys'), 'body' => __('Honeymoons, family reunions, private departures. Tell us the dream and a specialist designs it with you.')],
        ['icon' => 'banknotes', 'title' => __('Flexible, fair payments'), 'body' => __('Secure your seats with a 20% deposit and pay the balance 60 days before you go. No hidden fees.')],
    ];

    $testimonials = [
        ['quote' => __('Our guide in Tuscany got us into a cellar that isn\'t on any map. Every detail was handled. We have already booked Slovenia for next spring.'), 'name' => 'Priya & Daniel M.', 'trip' => __('Tuscany Slow Food & Wine')],
        ['quote' => __('I travelled solo and never felt alone. Twelve of us, one brilliant guide, and the glacier hike of my life.'), 'name' => 'Hannah K.', 'trip' => __('Iceland Ring Road Adventure')],
        ['quote' => __('They rebuilt our whole Cape Town itinerary in a day when our flights changed. Calm, quick and kind.'), 'name' => 'Marcus T.', 'trip' => __('Cape Town & Winelands')],
    ];
@endphp

<x-layouts::public>
    <main class="flex flex-1 flex-col">
        {{-- Hero --}}
        <section class="relative isolate overflow-hidden bg-neutral-900">
            @if ($heroDestination?->imageUrl() !== null)
                <img src="{{ $heroDestination->imageUrl() }}" alt="" class="absolute inset-0 -z-10 size-full object-cover" fetchpriority="high" />
            @endif
            <div class="absolute inset-0 -z-10 bg-linear-to-r from-black/75 via-black/45 to-black/10"></div>

            <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8 lg:py-36">
                <div class="max-w-2xl text-white">
                    <p class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-sm font-medium ring-1 ring-white/25 backdrop-blur">
                        <flux:icon.star variant="micro" class="text-orange-300" />
                        {{ __('Rated 4.9/5 by 2,300+ travellers') }}
                    </p>

                    <h1 class="mt-6 font-display text-4xl leading-tight font-semibold text-balance sm:text-6xl">
                        {{ __('Small-group journeys to the places you\'ll talk about for years.') }}
                    </h1>

                    <p class="mt-6 max-w-xl text-lg leading-relaxed text-white/85">
                        {{ __(':business plans guided tours and tailor-made trips across five continents, with local experts, hand-picked stays and one team looking after you from first question to flight home.', ['business' => $businessName]) }}
                    </p>
                </div>

                {{-- Search --}}
                <form
                    action="{{ route('tours.index') }}"
                    method="get"
                    class="mt-10 grid max-w-4xl gap-3 rounded-2xl bg-white p-3 shadow-2xl sm:grid-cols-[1fr_1fr_auto] dark:bg-neutral-900"
                    role="search"
                    aria-label="{{ __('Find a tour') }}"
                >
                    <label class="block rounded-xl px-3 py-2 hover:bg-neutral-50 dark:hover:bg-neutral-800">
                        <span class="block text-xs font-semibold tracking-wide text-neutral-500 uppercase">{{ __('Where to?') }}</span>
                        <select name="destination" class="mt-0.5 w-full border-0 bg-transparent p-0 text-base font-medium focus:ring-0 dark:bg-neutral-900">
                            <option value="">{{ __('Anywhere') }}</option>
                            @foreach ($destinations as $destination)
                                <option value="{{ $destination->slug }}">{{ $destination->place() }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block rounded-xl px-3 py-2 hover:bg-neutral-50 dark:hover:bg-neutral-800">
                        <span class="block text-xs font-semibold tracking-wide text-neutral-500 uppercase">{{ __('Travel style') }}</span>
                        <select name="style" class="mt-0.5 w-full border-0 bg-transparent p-0 text-base font-medium focus:ring-0 dark:bg-neutral-900">
                            <option value="">{{ __('Any style') }}</option>
                            @foreach ($styles as $style)
                                <option value="{{ $style->value }}">{{ __($style->label()) }}</option>
                            @endforeach
                        </select>
                    </label>

                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-orange-500 px-6 py-3 text-base font-semibold text-white transition hover:bg-orange-600">
                        <flux:icon.magnifying-glass variant="mini" />
                        {{ __('Search tours') }}
                    </button>
                </form>

                <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/85">
                    <li class="flex items-center gap-1.5"><flux:icon.check-circle variant="micro" class="text-teal-300" /> {{ __('Free changes up to 60 days out') }}</li>
                    <li class="flex items-center gap-1.5"><flux:icon.check-circle variant="micro" class="text-teal-300" /> {{ __('20% deposit secures your seats') }}</li>
                    <li class="flex items-center gap-1.5"><flux:icon.check-circle variant="micro" class="text-teal-300" /> {{ __('Reply within one business day') }}</li>
                </ul>
            </div>
        </section>

        {{-- Featured tours --}}
        <section class="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold tracking-widest text-teal-700 uppercase dark:text-teal-400">{{ __('Bestsellers') }}</p>
                    <h2 class="mt-2 font-display text-3xl font-semibold sm:text-4xl">{{ __('Tours our travellers love') }}</h2>
                </div>
                <flux:button :href="route('tours.index')" variant="ghost" icon-trailing="arrow-right" wire:navigate>{{ __('See all tours') }}</flux:button>
            </div>

            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featuredTours as $tour)
                    <x-travel.tour-card :tour="$tour" />
                @endforeach
            </div>
        </section>

        {{-- Destinations --}}
        <section class="bg-stone-50 py-16 sm:py-24 dark:bg-neutral-900/50">
            <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold tracking-widest text-teal-700 uppercase dark:text-teal-400">{{ __('Destinations') }}</p>
                        <h2 class="mt-2 font-display text-3xl font-semibold sm:text-4xl">{{ __('Where will you go next?') }}</h2>
                    </div>
                    <flux:button :href="route('destinations.index')" variant="ghost" icon-trailing="arrow-right" wire:navigate>{{ __('All destinations') }}</flux:button>
                </div>

                <div class="mt-10 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
                    @foreach ($destinations->take(8) as $destination)
                        <x-travel.destination-card :destination="$destination" />
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Services --}}
        <section id="services" class="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold tracking-widest text-teal-700 uppercase dark:text-teal-400">{{ __('Why travel with us') }}</p>
                <h2 class="mt-2 font-display text-3xl font-semibold sm:text-4xl">{{ __('Everything handled, nothing generic') }}</h2>
            </div>

            <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($services as $service)
                    <div class="flex gap-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-700 dark:bg-teal-400/10 dark:text-teal-300">
                            <flux:icon :icon="$service['icon']" />
                        </span>
                        <div>
                            <h3 class="font-semibold">{{ $service['title'] }}</h3>
                            <p class="mt-1 text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">{{ $service['body'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Testimonials --}}
        <section class="bg-teal-900 py-16 text-white sm:py-24">
            <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="font-display text-3xl font-semibold sm:text-4xl">{{ __('In their words') }}</h2>

                <div class="mt-10 grid gap-6 lg:grid-cols-3">
                    @foreach ($testimonials as $testimonial)
                        <figure class="flex flex-col rounded-2xl bg-white/5 p-6 ring-1 ring-white/10">
                            <div class="flex gap-0.5 text-orange-300" role="img" aria-label="{{ __('5 out of 5 stars') }}">
                                @for ($i = 0; $i < 5; $i++)
                                    <flux:icon.star variant="micro" />
                                @endfor
                            </div>
                            <blockquote class="mt-4 flex-1 leading-relaxed text-white/90">“{{ $testimonial['quote'] }}”</blockquote>
                            <figcaption class="mt-6 text-sm">
                                <span class="font-semibold">{{ $testimonial['name'] }}</span>
                                <span class="block text-white/60">{{ $testimonial['trip'] }}</span>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>

        {{--
            The latest posts, rendered only while the blog is switched on and
            holding at least one published post. $latestPosts arrives as a closure
            (see AppServiceProvider) so a blog that is off costs no query at all.
        --}}
        @php($latestPosts = $latestPosts())

        @if ($latestPosts !== null && $latestPosts->isNotEmpty())
            <section class="mx-auto w-full max-w-7xl px-4 pt-16 sm:px-6 sm:pt-24 lg:px-8">
                <div class="flex items-end justify-between gap-4">
                    <h2 class="font-display text-3xl font-semibold">{{ __('From the blog') }}</h2>
                    <a href="{{ route('blog.index') }}" class="text-sm font-medium text-teal-700 hover:underline dark:text-teal-400" wire:navigate>
                        {{ __('View all posts') }}
                    </a>
                </div>

                <div class="mt-8 grid gap-6 sm:grid-cols-3">
                    @foreach ($latestPosts as $post)
                        <div class="rounded-2xl border border-neutral-200 p-6 dark:border-neutral-800">
                            <p class="text-xs text-neutral-500">{{ app(\App\Settings\Settings::class)->formatDate($post->published_at) }}</p>
                            <h3 class="mt-2 font-semibold">
                                <a href="{{ route('blog.show', $post) }}" class="hover:underline" wire:navigate>{{ $post->title }}</a>
                            </h3>
                            <p class="mt-2 line-clamp-3 text-sm text-neutral-600 dark:text-neutral-400">{{ $post->excerpt() }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Call to action --}}
        <section class="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
            <div class="relative overflow-hidden rounded-3xl bg-linear-to-br from-orange-500 to-rose-500 px-6 py-12 text-white sm:px-12 sm:py-16">
                <div class="max-w-2xl">
                    <h2 class="font-display text-3xl font-semibold sm:text-4xl">{{ __('Can\'t find quite the right trip?') }}</h2>
                    <p class="mt-4 text-lg text-white/90">
                        {{ __('Tell us where, when and who\'s coming. A trip specialist will send you a tailor-made itinerary and price within two business days, free and with no obligation.') }}
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('plan-trip') }}" wire:navigate class="inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 font-semibold text-orange-600 shadow-sm hover:bg-orange-50">
                            {{ __('Plan my trip') }}
                            <flux:icon.arrow-right variant="micro" />
                        </a>
                        <a href="{{ route('contact') }}" wire:navigate class="inline-flex items-center gap-2 rounded-full px-6 py-3 font-semibold text-white ring-1 ring-white/60 hover:bg-white/10">
                            {{ __('Ask a question') }}
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>
</x-layouts::public>
