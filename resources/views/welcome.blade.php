{{--
    The home page, which is the menu.

    HomeController passes the available items grouped by category ($menu), up
    to three featured ones for the hero ($featured), and the photo credits the
    images' Creative Commons licences require ($credits). The layout is
    layouts/public.blade.php.

    The category chips filter client-side with Alpine: the whole menu is a
    dozen or two items, so every section is rendered and simply hidden, which
    keeps the page crawlable and works with JavaScript off.

    No :title is passed on purpose: the home page takes the site-wide SEO title.
--}}
<x-layouts::public>
    <main class="flex-1">
        {{-- Hero --}}
        <section class="grid items-center gap-12 py-10 lg:grid-cols-[1.1fr_1fr] lg:py-16">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full border border-amber-300/70 bg-amber-50 px-3 py-1 text-xs font-semibold tracking-wide text-amber-900 uppercase dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                    <span class="size-1.5 rounded-full bg-amber-600 dark:bg-amber-400"></span>
                    {{ __('Home bakery · Made to order') }}
                </p>

                <h1 class="font-display mt-6 text-5xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-6xl">
                    {{ __('Baked fresh, by hand,') }}
                    <span class="text-amber-700 italic dark:text-amber-400">{{ __('for the moments that matter.') }}</span>
                </h1>

                <p class="mt-6 max-w-xl text-lg leading-relaxed text-stone-600 dark:text-stone-400">
                    {{ __(':business is a small home bakery making slow-fermented breads, laminated pastries and celebration cakes in small batches. Browse the menu, send us an order request, and we will confirm every order personally within a day.', ['business' => $businessName]) }}
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a
                        href="#menu"
                        class="inline-flex items-center gap-2 rounded-full bg-amber-700 px-6 py-3 font-semibold text-white shadow-md shadow-amber-900/20 hover:bg-amber-800 dark:bg-amber-500 dark:text-stone-950 dark:hover:bg-amber-400"
                    >
                        {{ __('Browse the menu') }}
                        <flux:icon.arrow-down variant="micro" />
                    </a>
                    <a
                        href="{{ route('order') }}"
                        class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white/70 px-6 py-3 font-semibold text-stone-800 hover:border-stone-400 hover:bg-white dark:border-white/15 dark:bg-white/5 dark:text-stone-100 dark:hover:bg-white/10"
                        wire:navigate
                    >
                        {{ __('Request an order') }}
                    </a>
                </div>

                <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-stone-600 dark:text-stone-400">
                    @foreach ([
                        ['icon' => 'calendar-days', 'text' => __('Order 2+ days ahead')],
                        ['icon' => 'truck', 'text' => __('Pickup or local delivery')],
                        ['icon' => 'heart', 'text' => __('Vegan & gluten-free options')],
                    ] as $promise)
                        <li class="flex items-center gap-2">
                            <flux:icon
                                :icon="$promise['icon']"
                                variant="mini"
                                class="text-amber-600 dark:text-amber-400"
                            />
                            {{ $promise['text'] }}
                        </li>
                    @endforeach
                </ul>
            </div>

            @if ($featured->isNotEmpty())
                <div class="relative mx-auto w-full max-w-lg lg:max-w-none">
                    <div class="grid grid-cols-5 grid-rows-2 gap-3 sm:gap-4">
                        @foreach ($featured as $index => $item)
                            <a
                                href="{{ route('order', ['item' => $item->slug]) }}"
                                class="@if ($index === 0) col-span-3 row-span-2 @else col-span-2 @endif group relative overflow-hidden rounded-3xl bg-amber-100 shadow-lg shadow-amber-900/10 dark:bg-stone-800"
                                wire:navigate
                            >
                                @if ($item->imageUrl() !== null)
                                    <img
                                        src="{{ $item->imageUrl() }}"
                                        alt="{{ $item->name }}"
                                        class="@if ($index === 0) aspect-[3/4] @else aspect-square @endif size-full object-cover transition duration-500 group-hover:scale-105"
                                    />
                                @endif
                                <span class="absolute inset-x-2 bottom-2 rounded-xl bg-white/85 px-3 py-1.5 text-xs font-semibold text-stone-900 backdrop-blur dark:bg-stone-950/75 dark:text-stone-100">
                                    {{ $item->name }}
                                </span>
                            </a>
                        @endforeach
                    </div>

                    <div class="absolute -top-5 -left-4 hidden rotate-[-4deg] rounded-2xl border border-amber-200 bg-white px-4 py-3 shadow-xl shadow-amber-900/10 sm:block dark:border-white/10 dark:bg-stone-900">
                        <p class="font-display text-sm font-semibold">{{ __('Fresh this week') }}</p>
                        <p class="text-xs text-stone-500 dark:text-stone-400">
                            {{ __('Autumn pies are back on the menu') }}
                        </p>
                    </div>
                </div>
            @endif
        </section>

        {{-- Menu --}}
        <section id="menu" class="scroll-mt-8 py-12" x-data="{ category: 'all' }">
            <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 class="font-display text-4xl font-semibold tracking-tight">{{ __('The menu') }}</h2>
                    <p class="mt-2 max-w-xl text-stone-600 dark:text-stone-400">
                        {{ __('Everything is baked to order. Prices are a guide; we confirm the final price with your order.') }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('Filter the menu') }}">
                    <button
                        type="button"
                        x-on:click="category = 'all'"
                        x-bind:aria-pressed="category === 'all'"
                        x-bind:class="
                            category === 'all'
                                ? 'bg-stone-900 text-white dark:bg-amber-400 dark:text-stone-950'
                                : 'bg-white/70 text-stone-700 hover:bg-white dark:bg-white/5 dark:text-stone-300'
                        "
                        class="rounded-full border border-stone-200 px-4 py-1.5 text-sm font-medium dark:border-white/10"
                    >
                        {{ __('Everything') }}
                    </button>
                    @foreach ($menu as $section)
                        <button
                            type="button"
                            x-on:click="category = '{{ $section['category']->value }}'"
                            x-bind:aria-pressed="category === '{{ $section['category']->value }}'"
                            x-bind:class="category === '{{ $section['category']->value }}' ? 'bg-stone-900 text-white dark:bg-amber-400 dark:text-stone-950' : 'bg-white/70 text-stone-700 hover:bg-white dark:bg-white/5 dark:text-stone-300'"
                            class="rounded-full border border-stone-200 px-4 py-1.5 text-sm font-medium dark:border-white/10"
                        >
                            {{ __($section['category']->label()) }}
                        </button>
                    @endforeach
                </div>
            </div>

            @forelse ($menu as $section)
                <div
                    class="mt-12"
                    x-show="category === 'all' || category === '{{ $section['category']->value }}'"
                    x-transition.opacity
                    wire:key="menu-{{ $section['category']->value }}"
                >
                    <div class="flex items-baseline gap-4">
                        <h3 class="font-display text-2xl font-semibold">{{ __($section['category']->label()) }}</h3>
                        <p class="text-sm text-stone-500 italic dark:text-stone-400">
                            {{ __($section['category']->tagline()) }}
                        </p>
                    </div>

                    <div class="mt-5 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($section['items'] as $item)
                            <x-bakery.menu-card :item="$item" wire:key="menu-item-{{ $item->id }}" />
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="mt-12 rounded-2xl border border-dashed border-amber-300 p-10 text-center text-stone-600 dark:border-white/15 dark:text-stone-400">
                    {{ __('The menu is being written. Check back soon, or send us an order request for something special.') }}
                </p>
            @endforelse
        </section>

        {{-- How ordering works --}}
        <section class="py-12">
            <div class="rounded-3xl border border-amber-200/70 bg-white/70 p-8 sm:p-10 dark:border-white/10 dark:bg-stone-900/60">
                <h2 class="font-display text-3xl font-semibold tracking-tight">{{ __('How ordering works') }}</h2>

                <ol class="mt-8 grid gap-8 md:grid-cols-3">
                    @foreach ([
                        ['title' => __('Send a request'), 'body' => __('Pick your bakes, the date you need them and pickup or delivery. Tell us about the occasion, cake message or allergies.')],
                        ['title' => __('We confirm by email'), 'body' => __('Within a day we check the date against our baking calendar and reply with the final price and how to pay.')],
                        ['title' => __('Collect it warm'), 'body' => __('Everything is baked for your date, boxed and ready to pick up from our kitchen, or delivered locally.')],
                    ] as $step)
                        <li class="relative">
                            <span class="font-display flex size-10 items-center justify-center rounded-full bg-amber-700 text-lg font-semibold text-white dark:bg-amber-500 dark:text-stone-950">
                                {{ $loop->iteration }}
                            </span>
                            <h3 class="mt-4 text-lg font-semibold">{{ $step['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-stone-600 dark:text-stone-400">
                                {{ $step['body'] }}
                            </p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Call to action --}}
        <section class="pb-16">
            <div class="relative overflow-hidden rounded-3xl bg-stone-900 px-8 py-12 text-center text-white sm:px-16 dark:bg-amber-500/10">
                <div
                    class="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-amber-500/30 blur-3xl"
                    aria-hidden="true"
                ></div>
                <h2 class="font-display relative text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                    {{ __('Planning a birthday, wedding or office treat?') }}
                </h2>
                <p class="relative mx-auto mt-3 max-w-xl text-stone-300">
                    {{ __('Custom cakes need about a week. Tell us what you have in mind and we will make it happen.') }}
                </p>
                <a
                    href="{{ route('order') }}"
                    class="relative mt-8 inline-flex items-center gap-2 rounded-full bg-amber-400 px-6 py-3 font-semibold text-stone-950 hover:bg-amber-300"
                    wire:navigate
                >
                    {{ __('Start an order request') }}
                    <flux:icon.arrow-right variant="micro" />
                </a>
            </div>

            @if ($credits->isNotEmpty())
                <details class="mt-6 text-xs text-stone-500 dark:text-stone-500">
                    <summary class="cursor-pointer">{{ __('Photo credits') }}</summary>
                    <ul class="mt-2 space-y-1">
                        @foreach ($credits as $name => $credit)
                            <li>{{ $name }}: {{ $credit }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </section>
    </main>
</x-layouts::public>
