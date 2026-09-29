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
    <div class="relative min-h-dvh overflow-hidden bg-white text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <div
            class="pointer-events-none absolute -top-40 -right-32 h-[36rem] w-[36rem] rounded-full bg-linear-to-br from-plum-400/25 via-fuchsia-300/10 to-transparent blur-3xl"
            aria-hidden="true"
        ></div>
        <div
            class="pointer-events-none absolute -bottom-48 -left-40 h-[32rem] w-[32rem] rounded-full bg-linear-to-tr from-amber-300/20 via-plum-300/10 to-transparent blur-3xl"
            aria-hidden="true"
        ></div>

        <div class="relative mx-auto flex min-h-dvh w-full max-w-6xl flex-col px-6 lg:px-8">
            <header class="flex items-center justify-between gap-4 py-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-medium" wire:navigate>
                    <x-app-logo-icon class="size-8" />
                    <span class="font-display text-2xl font-semibold tracking-tight">{{ $businessName }}</span>
                </a>

                <nav aria-label="{{ __('Primary') }}" class="flex flex-wrap items-center justify-end gap-1 sm:gap-2">
                    <flux:button :href="route('perfumes.index')" size="sm" variant="ghost" wire:navigate>
                        {{ __('Perfumes') }}
                    </flux:button>

                    <flux:button :href="route('stats')" size="sm" variant="ghost" wire:navigate class="max-sm:hidden">
                        {{ __('Open stats') }}
                    </flux:button>

                    {{-- Shown only while the blog is switched on: the link
                         disappears alongside the routes EnsureBlogEnabled
                         closes, the same pairing as the sign-up link. --}}
                    @blogEnabled
                        <flux:button :href="route('blog.index')" size="sm" variant="ghost" wire:navigate>
                            {{ __('Blog') }}
                        </flux:button>
                    @endblogEnabled

                    <flux:button :href="route('contact')" size="sm" variant="ghost" wire:navigate class="max-sm:hidden">
                        {{ __('Contact') }}
                    </flux:button>

                    @if (Route::has('login'))
                        @auth
                            {{-- getPanels() rather than getPanel('admin'), which throws for an
                                 unregistered id and would 500 every signed-in visitor here. --}}
                            @php($adminPanel = filament()->getPanels()['admin'] ?? null)

                            @if ($adminPanel !== null && auth()->user()->canAccessPanel($adminPanel))
                                <flux:button
                                    :href="$adminPanel->getUrl()"
                                    size="sm"
                                    variant="primary"
                                    icon="wrench-screwdriver"
                                    icon-trailing="arrow-right"
                                >
                                    {{ __('Admin') }}
                                </flux:button>
                            @else
                                <flux:button
                                    :href="route('dashboard')"
                                    size="sm"
                                    variant="primary"
                                    icon-trailing="arrow-right"
                                    wire:navigate
                                >
                                    {{ __('Dashboard') }}
                                </flux:button>
                            @endif
                        @else
                            <flux:button :href="route('login')" size="sm" variant="ghost" wire:navigate>
                                {{ __('Log in') }}
                            </flux:button>

                            @registrationEnabled
                                @if (Route::has('register'))
                                    <flux:button :href="route('register')" size="sm" variant="primary" wire:navigate>
                                        {{ __('Join free') }}
                                    </flux:button>
                                @endif
                            @endregistrationEnabled
                        @endauth
                    @endif
                </nav>
            </header>

            {{ $slot }}

            <x-business-footer class="border-t border-neutral-200 py-8 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400" />
        </div>
    </div>
    @fluxScripts
</body>
</html>
