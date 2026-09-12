@props([
    'code',
    'title',
    'message',
    'icon' => 'exclamation-triangle',
    'tint' => 'from-zinc-500 to-zinc-700',
])

{{--
    The shell for every error page. Deliberately NOT built on
    x-layouts::app or x-layouts::auth, and deliberately not rendering
    partials.head.

    An error page must render when the application is broken, and a 500 is
    most often broken in the database: the View::composer('*') that supplies
    $businessName reads the settings table (App\Settings\Settings::all()), and
    partials.head reads six more settings plus the image disk. Extending a
    layout that depends on them means a database outage throws INSIDE the
    error view, and Laravel falls back to its own unstyled page -- precisely
    the moment these templates exist for.

    So the brand is read from config('app.name'), which is available from the
    cached config with no database behind it. This is the one intentional
    exception to the .ai/rules/views.md rule that views read $businessName: it
    is recorded there too, and the tradeoff is a stale brand on the error page
    of an instance that renamed itself, versus no styled error page at all.

    x-app-logo-icon IS safe to use here and is used: the global composer
    resolves Settings lazily and logoUrl() short-circuits on the unset default
    before it queries, so the uploaded-logo branch costs nothing when the
    database is unreachable. Verified by rendering this view against a
    nonexistent database. Anything added below that reads a setting, or the
    image disk with a logo stored, loses that property.

    @vite is still used, so the pages get the real design system. If the
    manifest is missing the ViteException surfaces here -- acceptable, because
    a missing manifest means the deploy is broken in a way the operator needs
    to see, and Laravel's fallback page still renders.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    {{-- Error pages must never be indexed, whatever the site's SEO setting. --}}
    <meta name="robots" content="noindex, nofollow" />

    <title>{{ $code }} &middot; {{ $title }} - {{ config('app.name') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any" />
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
    <div class="relative flex min-h-dvh flex-col overflow-hidden">
        {{-- Decorative only, matching the public page's treatment. --}}
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -top-40 -right-32 h-[36rem] w-[36rem] rounded-full bg-linear-to-br from-sky-400/20 via-indigo-400/10 to-transparent blur-3xl"
        ></div>
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -bottom-48 -left-40 h-[32rem] w-[32rem] rounded-full bg-linear-to-tr from-violet-400/20 via-sky-400/10 to-transparent blur-3xl"
        ></div>

        <div class="relative mx-auto flex min-h-dvh w-full max-w-2xl flex-col px-6 lg:px-8">
            <header class="py-8">
                {{--
                    A plain <a> to "/" rather than route('home'): a route cache
                    that failed to load is one of the ways a 500 happens, and
                    route() would throw inside the error page. No wire:navigate
                    either -- Livewire's script may not have loaded on the page
                    the user came from.
                --}}
                <a href="/" class="inline-flex items-center gap-2 font-medium text-neutral-900 dark:text-neutral-100">
                    <x-app-logo-icon class="size-7" />
                    <span>{{ config('app.name') }}</span>
                </a>
            </header>

            <main class="flex flex-1 flex-col justify-center py-12">
                <span class="flex size-12 items-center justify-center rounded-xl bg-linear-to-br {{ $tint }} text-white">
                    <flux:icon :icon="$icon" variant="outline" class="size-6" />
                </span>

                <p class="mt-6 font-mono text-sm font-semibold tracking-widest text-neutral-500 uppercase dark:text-neutral-400">
                    {{ __('Error :code', ['code' => $code]) }}
                </p>

                <h1 class="mt-2 text-4xl font-semibold tracking-tight text-balance text-neutral-900 sm:text-5xl dark:text-neutral-100">
                    {{ $title }}
                </h1>

                <p class="mt-5 max-w-prose text-lg leading-relaxed text-neutral-600 dark:text-neutral-400">
                    {{ $message }}
                </p>

                <div class="mt-10 flex flex-wrap items-center gap-3">{{ $actions ?? '' }}</div>
            </main>

            <footer class="border-t border-neutral-200 py-8 text-sm text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ config('app.name') }}</p>
            </footer>
        </div>
    </div>

    @fluxScripts
</body>
</html>
