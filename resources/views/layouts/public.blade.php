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
    dark: counterpart. Themed for the bakery: warm cream, amber and the Fraunces
    display face (font-display) for headings.
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
</head>
<body class="antialiased">
    <div class="bakery-theme relative min-h-dvh overflow-hidden bg-[#fffaf3] text-stone-900 dark:bg-stone-950 dark:text-stone-100">
        <div
            class="pointer-events-none absolute -top-40 -right-32 h-[36rem] w-[36rem] rounded-full bg-linear-to-br from-amber-300/30 via-orange-300/15 to-transparent blur-3xl dark:from-amber-500/15 dark:via-orange-500/10"
            aria-hidden="true"
        ></div>
        <div
            class="pointer-events-none absolute -bottom-48 -left-40 h-[32rem] w-[32rem] rounded-full bg-linear-to-tr from-rose-300/25 via-amber-200/15 to-transparent blur-3xl dark:from-rose-500/10 dark:via-amber-500/10"
            aria-hidden="true"
        ></div>

        @auth
            {{--
                While the holding page is up, the owner is the only one who sees
                this site -- say so, so nobody mistakes the preview for launch.
            --}}
            @if (app(\App\Settings\Settings::class)->boolean(\App\Settings\SettingKey::ComingSoon))
                <div class="relative z-10 bg-stone-900 px-4 py-2 text-center text-xs font-medium text-amber-100 dark:bg-amber-500/15">
                    {{ __('Coming soon mode is on: visitors see the holding page, you are seeing the real site.') }}
                    <a
                        href="{{ route('coming-soon') }}"
                        class="underline underline-offset-2"
                    >{{ __('View the holding page') }}</a>
                </div>
            @endif
        @endauth

        <div class="relative mx-auto flex min-h-dvh w-full max-w-6xl flex-col px-4 sm:px-6 lg:px-8">
            <header class="py-6 sm:py-8">
                <div class="flex items-center justify-between gap-4">
                    <a href="{{ route('home') }}" class="flex items-center gap-2.5" wire:navigate>
                        <x-app-logo-icon class="size-9" />
                        <span class="font-display text-xl font-semibold tracking-tight">{{ $businessName }}</span>
                    </a>

                    <nav aria-label="{{ __('Primary') }}" class="flex items-center gap-1 sm:gap-2">
                        <div class="hidden items-center gap-1 md:flex">
                            <a
                                href="{{ route('home') }}#menu"
                                class="rounded-full px-3 py-2 text-sm font-medium text-stone-700 hover:bg-amber-100/70 hover:text-stone-900 dark:text-stone-300 dark:hover:bg-white/5 dark:hover:text-white"
                            >{{ __('Menu') }}</a>
                            <a
                                href="{{ url('about') }}"
                                class="rounded-full px-3 py-2 text-sm font-medium text-stone-700 hover:bg-amber-100/70 hover:text-stone-900 dark:text-stone-300 dark:hover:bg-white/5 dark:hover:text-white"
                                wire:navigate
                            >{{ __('About us') }}</a>
                            <a
                                href="{{ route('contact') }}"
                                class="rounded-full px-3 py-2 text-sm font-medium text-stone-700 hover:bg-amber-100/70 hover:text-stone-900 dark:text-stone-300 dark:hover:bg-white/5 dark:hover:text-white"
                                wire:navigate
                            >{{ __('Contact') }}</a>
                        </div>

                        <a
                            href="{{ route('order') }}"
                            class="inline-flex items-center gap-1.5 rounded-full bg-amber-700 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-amber-900/20 hover:bg-amber-800 dark:bg-amber-500 dark:text-stone-950 dark:hover:bg-amber-400"
                            wire:navigate
                        >
                            <flux:icon.shopping-bag variant="micro" />
                            {{ __('Order') }}
                        </a>

                        @if (Route::has('login'))
                            @auth
                                @if (auth()->user()->is_admin)
                                    <flux:button
                                        :href="filament()->getPanel('admin')->getUrl()"
                                        size="sm"
                                        variant="ghost"
                                        icon="wrench-screwdriver"
                                    >
                                        {{ __('Admin') }}
                                    </flux:button>
                                @else
                                    <flux:button :href="route('dashboard')" size="sm" variant="ghost" wire:navigate>
                                        {{ __('Dashboard') }}
                                    </flux:button>
                                @endif
                            @else
                                <flux:button :href="route('login')" size="sm" variant="ghost" wire:navigate>
                                    {{ __('Log in') }}
                                </flux:button>

                                @registrationEnabled
                                    @if (Route::has('register'))
                                        <flux:button
                                            :href="route('register')"
                                            size="sm"
                                            variant="ghost"
                                            wire:navigate
                                            class="max-sm:hidden"
                                        >
                                            {{ __('Sign up') }}
                                        </flux:button>
                                    @endif
                                @endregistrationEnabled
                            @endauth
                        @endif
                    </nav>
                </div>

                {{-- The same links on a phone, as a row under the brand. --}}
                <nav aria-label="{{ __('Sections') }}" class="mt-4 flex gap-1 overflow-x-auto md:hidden">
                    <a
                        href="{{ route('home') }}#menu"
                        class="shrink-0 rounded-full border border-amber-200 bg-white/60 px-3 py-1.5 text-sm font-medium dark:border-white/10 dark:bg-white/5"
                    >{{ __('Menu') }}</a>
                    <a
                        href="{{ url('about') }}"
                        class="shrink-0 rounded-full border border-amber-200 bg-white/60 px-3 py-1.5 text-sm font-medium dark:border-white/10 dark:bg-white/5"
                        wire:navigate
                    >{{ __('About us') }}</a>
                    <a
                        href="{{ route('contact') }}"
                        class="shrink-0 rounded-full border border-amber-200 bg-white/60 px-3 py-1.5 text-sm font-medium dark:border-white/10 dark:bg-white/5"
                        wire:navigate
                    >{{ __('Contact') }}</a>
                </nav>
            </header>

            {{ $slot }}

            <x-business-footer class="border-t border-amber-200/70 py-8 text-stone-500 dark:border-stone-800 dark:text-stone-400" />
        </div>
    </div>
    @fluxScripts
</body>
</html>
