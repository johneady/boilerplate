@props([
    'code',
    'title',
    'message',
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

    LIGHT MODE ON PURPOSE. The signed-in shell is hardcoded dark (see
    .ai/rules/app.md), but these pages are deliberately the opposite: an error
    is already a jarring moment, and a bright page with a friendly figure
    reads as "this is fine, here is what happened" rather than as a crash.
    There is no <html class="dark"> here and no dark: variants below -- adding
    them back would put the pages at the mercy of the viewer's OS setting,
    which is exactly the inconsistency this avoids.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
<body class="min-h-screen bg-linear-to-b from-white to-neutral-50 antialiased">
    <div class="relative flex min-h-dvh flex-col overflow-hidden">
        {{-- Decorative only, matching the public page's treatment. --}}
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -top-40 -right-32 h-[36rem] w-[36rem] rounded-full bg-linear-to-br from-sky-300/30 via-indigo-300/20 to-transparent blur-3xl"
        ></div>
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -bottom-48 -left-40 h-[32rem] w-[32rem] rounded-full bg-linear-to-tr from-violet-300/30 via-sky-300/20 to-transparent blur-3xl"
        ></div>

        {{-- No min-h-dvh here: the outer wrapper already spans the viewport,
             and repeating it forced this column to full height too. --}}
        <div class="relative mx-auto flex w-full max-w-2xl flex-1 flex-col px-6 lg:px-8">
            <header class="pt-8 pb-4">
                {{--
                    A plain <a> to "/" rather than route('home'): a route cache
                    that failed to load is one of the ways a 500 happens, and
                    route() would throw inside the error page. No wire:navigate
                    either -- Livewire's script may not have loaded on the page
                    the user came from.
                --}}
                <a href="/" class="inline-flex items-center gap-2 font-medium text-neutral-900">
                    <x-app-logo-icon class="size-7" />
                    <span>{{ config('app.name') }}</span>
                </a>
            </header>

            {{--
                Top-aligned rather than justify-center: centring in a tall
                viewport left a large empty band under the header before the
                figure, which read as a broken page rather than a composed
                one. The content now starts just below the brand and the page
                simply ends where it ends.
            --}}
            <main class="flex flex-1 flex-col pt-2 pb-12">
                <x-errors.figure :status="$code" class="-ml-2" />

                <p class="mt-6 font-mono text-sm font-semibold tracking-widest text-neutral-500 uppercase">
                    {{ __('Error :code', ['code' => $code]) }}
                </p>

                <h1 class="mt-2 text-4xl font-semibold tracking-tight text-balance text-neutral-900 sm:text-5xl">
                    {{ $title }}
                </h1>

                <p class="mt-5 max-w-prose text-lg leading-relaxed text-neutral-600">{{ $message }}</p>

                <div class="mt-10 flex flex-wrap items-center gap-3">{{ $actions ?? '' }}</div>
            </main>

            <footer class="border-t border-neutral-200 py-8 text-sm text-neutral-500">
                <p class="font-medium text-neutral-900">{{ config('app.name') }}</p>
            </footer>
        </div>
    </div>

    @fluxScripts
</body>
</html>
