@props([
    'title' => null,
    'description' => null,
])

{{--
    The shell every public page renders inside: the marketing home page, the
    administrator-authored content pages and the contact form.

    Extracted from welcome.blade.php, which was a standalone HTML document back
    when it was the only public page. Four pages sharing one shell is worth a
    layout; four copies of the same <head> and footer is how the brand mark ends
    up updated in three of them.

    Unlike the signed-in shell (layouts/app/sidebar.blade.php, hardcoded dark)
    this follows the visitor's own colour scheme, so every colour here needs its
    dark: counterpart.
--}}
@php
    // partials/head builds the <title> from $title, so a page's title reaches it
    // as view data. Null for a page that passes none, which leaves the head on
    // the site-wide SEO title rather than a stray "- Business" suffix.
    $title = filled($title) ? $title : null;

    // The description is passed into the @include below rather than set here:
    // a View::composer on partials.head supplies $seoDescription from the
    // settings, and a composer runs AFTER this block and would overwrite it.
    // Data passed to @include wins over composed data, which is what lets a
    // page override the site-wide description. Null falls through to the setting.
    $pageDescription = filled($description) ? $description : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', array_filter(['seoDescription' => $pageDescription]))

    {{-- Feed autodiscovery, here rather than in partials.head so only the
         public shell advertises it, and gated like the header link so it
         never points a reader at a route EnsureBlogEnabled would 404. --}}
    @blogEnabled
        <link
            rel="alternate"
            type="application/atom+xml"
            title="{{ __(':business — Blog', ['business' => $businessName]) }}"
            href="{{ route('blog.feed') }}"
        />
    @endblogEnabled
</head>
<body class="antialiased">
    {{--
        Wanderlight's public shell. The header is full-width and sits over the
        page; pages that open with a photo hero pull themselves up beneath it.
        Below lg the links fold into a menu toggled with Alpine, so every page
        is one tap from the tours and the booking form on a phone.
    --}}
    <div class="flex min-h-dvh flex-col bg-white text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <header
            x-data="{ open: false }"
            x-on:keydown.escape.window="open = false"
            class="sticky top-0 z-40 border-b border-neutral-200/70 bg-white/90 backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/90"
        >
            <div class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5" wire:navigate>
                    <x-app-logo-icon class="size-9" />
                    <span class="font-display text-xl font-semibold tracking-tight">{{ $businessName }}</span>
                </a>

                @php
                    $businessPhone = app(\App\Settings\Settings::class)->string(\App\Settings\SettingKey::BusinessPhone);

                    $primaryLinks = [
                        ['label' => __('Destinations'), 'url' => route('destinations.index'), 'active' => request()->routeIs('destinations.*')],
                        ['label' => __('Tours'), 'url' => route('tours.index'), 'active' => request()->routeIs('tours.*')],
                        ['label' => __('Tailor-made'), 'url' => route('plan-trip'), 'active' => request()->routeIs('plan-trip')],
                        ['label' => __('Contact'), 'url' => route('contact'), 'active' => request()->routeIs('contact')],
                    ];

                    // Shown only while the blog is switched on: the link
                    // disappears alongside the routes EnsureBlogEnabled closes.
                    if (app(\App\Blog\BlogManager::class)->enabled()) {
                        array_splice($primaryLinks, 3, 0, [['label' => __('Blog'), 'url' => route('blog.index'), 'active' => request()->routeIs('blog.*')]]);
                    }

                    // getPanels() rather than getPanel('admin'), which throws
                    // for an unregistered id and would 500 every signed-in visitor.
                    $adminPanel = filament()->getPanels()['admin'] ?? null;
                    $canAccessAdmin = auth()->check() && $adminPanel !== null && auth()->user()->canAccessPanel($adminPanel);
                @endphp

                <nav aria-label="{{ __('Primary') }}" class="hidden items-center gap-1 lg:flex">
                    @foreach ($primaryLinks as $link)
                        <a
                            href="{{ $link['url'] }}"
                            wire:navigate
                            @class([
                                'rounded-full px-4 py-2 text-sm font-medium transition',
                                'bg-teal-50 text-teal-800 dark:bg-teal-400/10 dark:text-teal-300' => $link['active'],
                                'text-neutral-700 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800' => ! $link['active'],
                            ])
                        >
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="flex items-center gap-2">
                    @if ($businessPhone !== '')
                        <a
                            href="tel:{{ preg_replace('/[^0-9+]/', '', $businessPhone) }}"
                            class="hidden items-center gap-1.5 text-sm font-medium text-neutral-600 hover:text-teal-700 xl:flex dark:text-neutral-400 dark:hover:text-teal-300"
                        >
                            <flux:icon.phone variant="micro" />
                            {{ $businessPhone }}
                        </a>
                    @endif

                    @auth
                        @if ($canAccessAdmin)
                            <flux:button :href="$adminPanel->getUrl()" size="sm" variant="ghost" icon="wrench-screwdriver" class="hidden sm:inline-flex">
                                {{ __('Admin') }}
                            </flux:button>
                        @else
                            <flux:button :href="route('dashboard')" size="sm" variant="ghost" icon="user-circle" class="hidden sm:inline-flex" wire:navigate>
                                {{ __('My trips') }}
                            </flux:button>
                        @endif
                    @else
                        @if (Route::has('login'))
                            <flux:button :href="route('login')" size="sm" variant="ghost" class="hidden sm:inline-flex" wire:navigate>
                                {{ __('Log in') }}
                            </flux:button>
                        @endif

                        @registrationEnabled
                            @if (Route::has('register'))
                                <flux:button :href="route('register')" size="sm" variant="ghost" class="hidden md:inline-flex" wire:navigate>
                                    {{ __('Sign up') }}
                                </flux:button>
                            @endif
                        @endregistrationEnabled
                    @endauth

                    <a
                        href="{{ route('tours.index') }}"
                        wire:navigate
                        class="hidden rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-600 sm:inline-flex"
                    >
                        {{ __('Find a tour') }}
                    </a>

                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full text-neutral-700 hover:bg-neutral-100 lg:hidden dark:text-neutral-300 dark:hover:bg-neutral-800"
                        x-on:click="open = ! open"
                        x-bind:aria-expanded="open"
                        aria-controls="mobile-menu"
                    >
                        <span class="sr-only">{{ __('Menu') }}</span>
                        <flux:icon.bars-3 x-show="! open" />
                        <flux:icon.x-mark x-show="open" x-cloak />
                    </button>
                </div>
            </div>

            <nav
                id="mobile-menu"
                aria-label="{{ __('Mobile') }}"
                x-show="open"
                x-cloak
                x-transition.opacity
                class="border-t border-neutral-200 px-4 pt-2 pb-4 lg:hidden dark:border-neutral-800"
            >
                @foreach ($primaryLinks as $link)
                    <a href="{{ $link['url'] }}" wire:navigate class="block rounded-lg px-3 py-3 text-base font-medium hover:bg-neutral-100 dark:hover:bg-neutral-800">
                        {{ $link['label'] }}
                    </a>
                @endforeach

                <div class="mt-3 grid grid-cols-2 gap-2">
                    @auth
                        @if ($canAccessAdmin)
                            <flux:button :href="$adminPanel->getUrl()" variant="filled">{{ __('Admin') }}</flux:button>
                        @else
                            <flux:button :href="route('dashboard')" variant="filled" wire:navigate>{{ __('My trips') }}</flux:button>
                        @endif
                    @else
                        <flux:button :href="route('login')" variant="filled" wire:navigate>{{ __('Log in') }}</flux:button>
                    @endauth
                    <a href="{{ route('tours.index') }}" wire:navigate class="inline-flex items-center justify-center rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white">
                        {{ __('Find a tour') }}
                    </a>
                </div>
            </nav>
        </header>

        <div class="flex flex-1 flex-col">
            {{ $slot }}
        </div>

        <div class="bg-neutral-950 text-neutral-400">
            <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-business-footer class="py-10" />
            </div>
        </div>
    </div>
    @fluxScripts
</body>
</html>
