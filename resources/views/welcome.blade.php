{{--
    The storefront home page, rendered by App\Http\Controllers\HomeController.
    The HTML shell, header nav and footer are layouts/public.blade.php.

    No :title is passed on purpose: the home page takes the site-wide SEO title
    rather than prefixing it with a page name.
--}}
<x-layouts::public>
    <main class="flex-1">
        <section class="grid items-center gap-12 py-12 lg:grid-cols-2 lg:py-20">
            <div>
                <flux:badge
                    size="sm"
                    color="sky"
                    inset="top bottom"
                >{{ __('Licensed 4K & 5.4K aerial footage') }}</flux:badge>

                <h1 class="mt-6 text-5xl font-semibold tracking-tight text-balance sm:text-6xl">
                    {{ __('The world from above, ready for your next project.') }}
                </h1>

                <p class="mt-6 max-w-xl text-lg leading-relaxed text-neutral-600 dark:text-neutral-400">
                    {{ __(':business sells cinematic drone footage from iconic locations. Every location is its own package of ready-to-edit clips, with a royalty-free commercial licence and instant download.', ['business' => $businessName]) }}
                </p>

                <div class="mt-10 flex flex-wrap items-center gap-3">
                    <flux:button
                        :href="route('shop.index')"
                        variant="primary"
                        icon-trailing="arrow-right"
                        wire:navigate
                    >
                        {{ __('Browse packages') }}
                    </flux:button>
                    <flux:button href="#how-it-works" variant="ghost">{{ __('How it works') }}</flux:button>
                </div>

                <dl class="mt-12 grid max-w-md grid-cols-3 gap-6 border-t border-neutral-200 pt-8 dark:border-neutral-800">
                    <div>
                        <dt class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Locations') }}</dt>
                        <dd class="mt-1 text-2xl font-semibold">{{ $packageCount }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Regions') }}</dt>
                        <dd class="mt-1 text-2xl font-semibold">{{ $regions->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Delivery') }}</dt>
                        <dd class="mt-1 text-2xl font-semibold">{{ __('Instant') }}</dd>
                    </div>
                </dl>
            </div>

            @if ($featured->isNotEmpty())
                <div class="relative grid grid-cols-6 grid-rows-6 gap-4 max-lg:h-96 lg:h-120" aria-hidden="true">
                    @foreach ($featured as $index => $package)
                        @if ($package->imageUrl() !== null)
                            <img
                                src="{{ $package->imageUrl() }}"
                                alt=""
                                @class([
                                    'size-full rounded-2xl object-cover shadow-xl ring-1 ring-black/5',
                                    'col-span-4 row-span-6' => $loop->first,
                                    'col-span-2 row-span-3' => ! $loop->first,
                                ])
                            />
                        @endif
                    @endforeach
                </div>
            @endif
        </section>

        @if ($featured->isNotEmpty())
            <section class="py-12" aria-labelledby="featured-heading">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 id="featured-heading" class="text-3xl font-semibold tracking-tight">
                            {{ __('Featured packages') }}
                        </h2>
                        <p class="mt-2 text-neutral-600 dark:text-neutral-400">
                            {{ __('Our most popular locations, hand-picked this season.') }}
                        </p>
                    </div>
                    <flux:button :href="route('shop.index')" variant="ghost" icon-trailing="arrow-right" wire:navigate>
                        {{ __('View all packages') }}
                    </flux:button>
                </div>

                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($featured as $package)
                        <x-shop.package-card :package="$package" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($regions->isNotEmpty())
            <section class="py-12" aria-labelledby="regions-heading">
                <h2 id="regions-heading" class="text-3xl font-semibold tracking-tight">{{ __('Browse by region') }}</h2>

                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    @foreach ($regions as $entry)
                        <a
                            href="{{ route('shop.index', ['region' => $entry['region']->value]) }}"
                            wire:navigate
                            class="group rounded-2xl border border-neutral-200 bg-white/60 p-5 transition hover:border-sky-400 hover:shadow-md dark:border-neutral-800 dark:bg-neutral-900/40 dark:hover:border-sky-500"
                        >
                            <flux:icon.globe-europe-africa class="size-6 text-sky-600 dark:text-sky-400" />
                            <p class="mt-3 font-semibold group-hover:underline">{{ __($entry['region']->label()) }}</p>
                            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                                {{ trans_choice(':count package|:count packages', $entry['count'], ['count' => $entry['count']]) }}
                            </p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section id="how-it-works" class="scroll-mt-8 py-12" aria-labelledby="how-heading">
            <h2 id="how-heading" class="text-3xl font-semibold tracking-tight">{{ __('How it works') }}</h2>

            <ol class="mt-8 grid gap-6 sm:grid-cols-3">
                @foreach ([
                    ['icon' => 'map', 'heading' => __('Choose a location'), 'body' => __('Each package covers one location, with preview stills, the full shot list and exact specs.')],
                    ['icon' => 'credit-card', 'heading' => __('Check out in one step'), 'body' => __('No account needed. Add packages to your basket, enter your email and pay securely.')],
                    ['icon' => 'arrow-down-tray', 'heading' => __('Download instantly'), 'body' => __('Original files and a licence certificate arrive by email the moment your order is placed.')],
                ] as $step)
                    <li class="rounded-2xl border border-neutral-200 bg-white/60 p-6 dark:border-neutral-800 dark:bg-neutral-900/40">
                        <span class="flex size-10 items-center justify-center rounded-full bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-400">
                            <flux:icon :name="$step['icon']" variant="mini" />
                        </span>
                        <flux:heading size="lg" class="mt-4">{{ $step['heading'] }}</flux:heading>
                        <p class="mt-2 text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">
                            {{ $step['body'] }}
                        </p>
                    </li>
                @endforeach
            </ol>
        </section>

        <section
            class="my-12 overflow-hidden rounded-3xl bg-neutral-900 px-8 py-12 text-white sm:px-12 dark:bg-neutral-800"
            aria-labelledby="custom-heading"
        >
            <div class="grid items-center gap-8 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <h2 id="custom-heading" class="text-3xl font-semibold tracking-tight">
                        {{ __('Need a location we have not filmed yet?') }}
                    </h2>
                    <p class="mt-3 text-neutral-300">
                        {{ __('We take on custom shoots for agencies, tourism boards and production companies — licensed pilots, full insurance and fast turnaround.') }}
                    </p>
                </div>
                <div class="lg:text-right">
                    <flux:button
                        :href="route('contact')"
                        variant="primary"
                        wire:navigate
                    >{{ __('Request a custom shoot') }}</flux:button>
                </div>
            </div>
        </section>
    </main>
</x-layouts::public>
