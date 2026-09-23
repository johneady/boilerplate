{{--
    The site header, as laid out in the brief:

    VOLTIVA MOBILITY | Cars ▾ | Why Voltiva ▾ | News & Advice | About ▾ | Find Your Car | VIEW CARS

    The Cars menu lists the published range with a photo of each car
    ($navVehicles, composed in AppServiceProvider), so a car added in the
    admin panel appears here with no design work. The menus open on hover
    and on click/Enter, and close on Escape or a click elsewhere.

    Mobile gets the brief's "simple menu": one full-screen list, grouped with
    native <details> so it works before any JavaScript does.
--}}
@php
    $whyVoltiva = [
        ['slug' => 'batteries-and-range', 'label' => __('Batteries & Range'), 'hint' => __('How far you can go, and for how long')],
        ['slug' => 'charging', 'label' => __('Charging'), 'hint' => __('From a normal socket at home')],
        ['slug' => 'registration', 'label' => __('Registration'), 'hint' => __('Paperwork and plates, done for you')],
        ['slug' => 'servicing-and-support', 'label' => __('Servicing & Support'), 'hint' => __('Local servicing across Mallorca')],
        ['slug' => 'finance', 'label' => __('Finance'), 'hint' => __('Spread the cost with monthly payments')],
        ['slug' => 'accessories', 'label' => __('Accessories'), 'hint' => __('Chargers, covers, storage and more')],
    ];

    $about = [
        ['href' => route('pages.show', 'about'), 'label' => __('About Voltiva')],
        ['href' => route('pages.show', 'faq'), 'label' => __('FAQ')],
        ['href' => route('contact'), 'label' => __('Contact')],
    ];

    $locales = (array) config('voltiva.locales');
    $currentLocale = app()->getLocale();

    $linkClass = 'inline-flex items-center gap-1 px-3 py-2 text-sm text-neutral-800 transition hover:text-neutral-950';
@endphp

<header
    class="sticky top-0 z-40 border-b border-neutral-200 bg-white/95 backdrop-blur supports-backdrop-filter:bg-white/85"
    x-data="{ open: null, mobile: false }"
    @keydown.escape.window="
        open = null;
        mobile = false;
    "
    @click.outside="open = null"
>
    <div class="mx-auto flex h-16 max-w-360 items-center gap-6 px-4 sm:px-6 lg:px-10">
        {{-- The mark is the logo uploaded in Settings (the bundled one until then), so a new logo reaches the site with no design work. --}}
        <a
            href="{{ route('home') }}"
            class="flex shrink-0 items-center gap-2.5 text-[13px] font-semibold tracking-[0.28em] text-neutral-950 uppercase"
        >
            <x-app-logo-icon class="size-6" />
            {{ $businessName }}
        </a>

        <nav aria-label="{{ __('Primary') }}" class="hidden flex-1 items-stretch self-stretch lg:flex">
            {{-- Cars --}}
            <div class="flex items-center" @mouseenter="open = 'cars'" @mouseleave="open = null">
                <button
                    type="button"
                    class="{{ $linkClass }}"
                    @click="open = open === 'cars' ? null : 'cars'"
                    :aria-expanded="open === 'cars'"
                    aria-haspopup="true"
                >
                    {{ __('Cars') }}
                    <flux:icon.chevron-down
                        variant="micro"
                        class="text-neutral-500 transition"
                        ::class="open === 'cars' && 'rotate-180'"
                    />
                </button>

                <div
                    x-cloak
                    x-show="open === 'cars'"
                    x-transition.opacity.duration.150ms
                    class="absolute inset-x-0 top-full border-b border-neutral-200 bg-white shadow-sm"
                >
                    <div class="mx-auto grid max-w-360 gap-10 px-10 py-8 lg:grid-cols-[1fr_16rem]">
                        <ul class="grid grid-cols-2 gap-6 xl:grid-cols-4">
                            @foreach ($navVehicles as $navVehicle)
                                <li>
                                    <a href="{{ route('cars.show', $navVehicle) }}" class="group block">
                                        <div class="aspect-4/3 overflow-hidden bg-neutral-100">
                                            @if ($navVehicle->imageUrl())
                                                <img
                                                    src="{{ $navVehicle->imageUrl() }}"
                                                    alt=""
                                                    loading="lazy"
                                                    decoding="async"
                                                    class="size-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                                />
                                            @endif
                                        </div>
                                        <p class="mt-3 text-sm font-medium">{{ $navVehicle->name }}</p>
                                        <p class="text-xs text-neutral-500">
                                            {{ $navVehicle->category->label() }} · {{ __('From :price', ['price' => $navVehicle->formattedPrice()]) }}
                                        </p>
                                    </a>
                                </li>
                            @endforeach
                        </ul>

                        <ul class="space-y-1 border-l border-neutral-200 pl-8 text-sm">
                            <li>
                                <a
                                    href="{{ route('cars.category', 'l6e') }}"
                                    class="hover:text-volt-700 block py-2"
                                >{{ __('L6e Electric Cars') }}</a>
                            </li>
                            <li>
                                <a
                                    href="{{ route('cars.category', 'l7e') }}"
                                    class="hover:text-volt-700 block py-2"
                                >{{ __('L7e Electric Cars') }}</a>
                            </li>
                            <li>
                                <a
                                    href="{{ route('compare') }}"
                                    class="hover:text-volt-700 block py-2"
                                >{{ __('Compare Cars') }}</a>
                            </li>
                            <li class="pt-3">
                                <a
                                    href="{{ route('cars.index') }}"
                                    class="hover:text-volt-700 inline-flex items-center gap-1 py-2 font-medium"
                                >
                                    {{ __('View all cars') }} <flux:icon.arrow-right variant="micro" />
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Why Voltiva --}}
            <div class="flex items-center" @mouseenter="open = 'why'" @mouseleave="open = null">
                <button
                    type="button"
                    class="{{ $linkClass }}"
                    @click="open = open === 'why' ? null : 'why'"
                    :aria-expanded="open === 'why'"
                    aria-haspopup="true"
                >
                    {{ __('Why Voltiva') }}
                    <flux:icon.chevron-down
                        variant="micro"
                        class="text-neutral-500 transition"
                        ::class="open === 'why' && 'rotate-180'"
                    />
                </button>

                <div
                    x-cloak
                    x-show="open === 'why'"
                    x-transition.opacity.duration.150ms
                    class="absolute inset-x-0 top-full border-b border-neutral-200 bg-white shadow-sm"
                >
                    <ul class="mx-auto grid max-w-360 grid-cols-3 gap-x-10 gap-y-2 px-10 py-8">
                        @foreach ($whyVoltiva as $item)
                            <li>
                                <a href="{{ route('pages.show', $item['slug']) }}" class="group block py-3">
                                    <span class="group-hover:text-volt-700 text-sm font-medium">{{ $item['label'] }}</span>
                                    <span class="mt-0.5 block text-sm text-neutral-500">{{ $item['hint'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <a href="{{ route('news.index') }}" class="{{ $linkClass }} self-center">{{ __('News & Advice') }}</a>

            {{-- About --}}
            <div class="relative flex items-center" @mouseenter="open = 'about'" @mouseleave="open = null">
                <button
                    type="button"
                    class="{{ $linkClass }}"
                    @click="open = open === 'about' ? null : 'about'"
                    :aria-expanded="open === 'about'"
                    aria-haspopup="true"
                >
                    {{ __('About') }}
                    <flux:icon.chevron-down
                        variant="micro"
                        class="text-neutral-500 transition"
                        ::class="open === 'about' && 'rotate-180'"
                    />
                </button>

                <div
                    x-cloak
                    x-show="open === 'about'"
                    x-transition.opacity.duration.150ms
                    class="absolute top-full left-0 w-56 border border-neutral-200 bg-white py-2 shadow-sm"
                >
                    @foreach ($about as $item)
                        <a
                            href="{{ $item['href'] }}"
                            class="hover:text-volt-700 block px-4 py-2 text-sm hover:bg-neutral-50"
                        >{{ $item['label'] }}</a>
                    @endforeach
                </div>
            </div>

            <a href="{{ route('finder') }}" class="{{ $linkClass }} self-center">{{ __('Find Your Car') }}</a>
        </nav>

        <div class="ml-auto flex items-center gap-2 lg:ml-0">
            {{-- Language: a text button, as asked, listing each language in its own name. --}}
            <div class="relative">
                <button
                    type="button"
                    class="inline-flex items-center gap-1 px-2 py-2 text-xs font-medium tracking-widest text-neutral-700 uppercase hover:text-neutral-950"
                    @click="open = open === 'lang' ? null : 'lang'"
                    :aria-expanded="open === 'lang'"
                    aria-haspopup="true"
                    aria-label="{{ __('Language: :language', ['language' => $locales[$currentLocale] ?? $currentLocale]) }}"
                >
                    <flux:icon.language variant="micro" />
                    {{ $currentLocale }}
                </button>

                <div
                    x-cloak
                    x-show="open === 'lang'"
                    x-transition.opacity.duration.150ms
                    class="absolute top-full right-0 w-40 border border-neutral-200 bg-white py-2 shadow-sm"
                >
                    @foreach ($locales as $code => $language)
                        <a
                            href="{{ route('locale', $code) }}"
                            hreflang="{{ $code }}"
                            lang="{{ $code }}"
                            @class([
                                'flex items-center justify-between px-4 py-2 text-sm hover:bg-neutral-50',
                                'font-medium text-volt-700' => $code === $currentLocale,
                            ])
                        >
                            {{ $language }}
                            <span class="text-xs tracking-widest text-neutral-400 uppercase">{{ $code }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <a
                href="{{ route('cars.index') }}"
                class="hidden bg-neutral-950 px-5 py-2.5 text-xs font-medium tracking-[0.18em] text-white uppercase transition hover:bg-neutral-800 sm:inline-block"
            >
                {{ __('View cars') }}
            </a>

            <button
                type="button"
                class="-mr-2 p-2 lg:hidden"
                @click="mobile = true"
                aria-controls="mobile-menu"
                :aria-expanded="mobile"
            >
                <span class="sr-only">{{ __('Open menu') }}</span>
                <flux:icon.bars-3 />
            </button>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div
        id="mobile-menu"
        x-cloak
        x-show="mobile"
        x-transition.opacity.duration.150ms
        class="fixed inset-0 z-50 overflow-y-auto bg-white lg:hidden"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('Menu') }}"
    >
        <div class="flex h-16 items-center justify-between border-b border-neutral-200 px-4 sm:px-6">
            <span class="text-[13px] font-semibold tracking-[0.28em] uppercase">{{ $businessName }}</span>
            <button type="button" class="-mr-2 p-2" @click="mobile = false">
                <span class="sr-only">{{ __('Close menu') }}</span>
                <flux:icon.x-mark />
            </button>
        </div>

        <nav aria-label="{{ __('Mobile') }}" class="divide-y divide-neutral-200 px-4 text-lg sm:px-6">
            <details class="group py-4">
                <summary class="flex cursor-pointer list-none items-center justify-between">
                    {{ __('Cars') }} <flux:icon.chevron-down class="size-5 transition group-open:rotate-180" />
                </summary>
                <ul class="mt-3 space-y-3 text-base text-neutral-700">
                    @foreach ($navVehicles as $navVehicle)
                        <li>
                            <a href="{{ route('cars.show', $navVehicle) }}"
                                >{{ $navVehicle->name }}
                                <span class="text-neutral-400">· {{ $navVehicle->category->label() }}</span></a>
                        </li>
                    @endforeach
                    <li><a href="{{ route('cars.category', 'l6e') }}">{{ __('L6e Electric Cars') }}</a></li>
                    <li><a href="{{ route('cars.category', 'l7e') }}">{{ __('L7e Electric Cars') }}</a></li>
                    <li><a href="{{ route('compare') }}">{{ __('Compare Cars') }}</a></li>
                </ul>
            </details>
            <details class="group py-4">
                <summary class="flex cursor-pointer list-none items-center justify-between">
                    {{ __('Why Voltiva') }} <flux:icon.chevron-down class="size-5 transition group-open:rotate-180" />
                </summary>
                <ul class="mt-3 space-y-3 text-base text-neutral-700">
                    @foreach ($whyVoltiva as $item)
                        <li><a href="{{ route('pages.show', $item['slug']) }}">{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>
            </details>
            <a href="{{ route('news.index') }}" class="block py-4">{{ __('News & Advice') }}</a>
            <details class="group py-4">
                <summary class="flex cursor-pointer list-none items-center justify-between">
                    {{ __('About') }} <flux:icon.chevron-down class="size-5 transition group-open:rotate-180" />
                </summary>
                <ul class="mt-3 space-y-3 text-base text-neutral-700">
                    @foreach ($about as $item)
                        <li><a href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>
            </details>
            <a href="{{ route('finder') }}" class="block py-4">{{ __('Find Your Car') }}</a>
            <div class="flex flex-wrap gap-2 py-6">
                @foreach ($locales as $code => $language)
                    <a
                        href="{{ route('locale', $code) }}"
                        lang="{{ $code }}"
                        @class([
                            'border px-3 py-1.5 text-sm',
                            'border-neutral-950 bg-neutral-950 text-white' => $code === $currentLocale,
                            'border-neutral-300' => $code !== $currentLocale,
                        ])
                    >{{ $language }}</a>
                @endforeach
            </div>
        </nav>

        <div class="px-4 pb-10 sm:px-6">
            <a
                href="{{ route('cars.index') }}"
                class="block bg-neutral-950 px-5 py-4 text-center text-sm font-medium tracking-[0.18em] text-white uppercase"
            >
                {{ __('View cars') }}
            </a>
        </div>
    </div>
</header>
