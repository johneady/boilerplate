{{--
    The index for the local error-page preview. Not an error page itself, so
    it may use the app's normal chrome -- but it is kept deliberately close to
    x-errors.layout so the pages it links to are seen in context.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <title>{{ __('Error page preview') }} - {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-linear-to-b from-white to-neutral-50 antialiased">
    {{-- Light mode, like the pages it links to (see x-errors.layout). --}}
    <div class="relative flex min-h-dvh flex-col overflow-hidden">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -top-40 -right-32 h-[36rem] w-[36rem] rounded-full bg-linear-to-br from-sky-300/30 via-indigo-300/20 to-transparent blur-3xl"
        ></div>

        <div class="relative mx-auto flex w-full max-w-2xl flex-1 flex-col px-6 lg:px-8">
            <header class="pt-8 pb-4">
                <a href="/" class="inline-flex items-center gap-2 font-medium text-neutral-900">
                    <x-app-logo-icon class="size-7" />
                    <span>{{ config('app.name') }}</span>
                </a>
            </header>

            {{-- Top-aligned, like x-errors.layout. --}}
            <main class="flex flex-1 flex-col pt-2 pb-12">
                {{-- Wrapped so the badge shrinks to its content: as a direct child
                 of the flex column it stretches to the full column width. --}}
                <div class="flex">
                    <flux:badge size="sm" color="amber" inset="top bottom">{{ __('Local preview') }}</flux:badge>
                </div>

                <h1 class="mt-4 text-4xl font-semibold tracking-tight text-neutral-900 sm:text-5xl">
                    {{ __('Error pages') }}
                </h1>

                <p class="mt-4 max-w-prose text-lg leading-relaxed text-neutral-600">
                    {{ __('Each page as a user would see it. This route is registered outside production only, and every preview returns 200 so the browser does not treat the preview itself as broken.') }}
                </p>

                <div class="mt-10 grid gap-3 sm:grid-cols-2">
                    @foreach ($statuses as $status)
                        <a
                            href="{{ route('dev.errors.show', $status) }}"
                            class="group flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-lg"
                        >
                            <span class="font-mono text-2xl font-semibold text-neutral-900"> {{ $status }} </span>
                            <span class="flex-1 text-sm text-neutral-600">
                                @switch ($status)
                                    @case (403)
                                        {{ __('Forbidden') }}
                                        @break
                                    @case (404)
                                        {{ __('Not found') }}
                                        @break
                                    @case (419)
                                        {{ __('Session expired') }}
                                        @break
                                    @case (429)
                                        {{ __('Too many requests') }}
                                        @break
                                    @case (500)
                                        {{ __('Server error') }}
                                        @break
                                    @case (503)
                                        {{ __('Maintenance') }}
                                        @break
                                @endswitch
                            </span>
                            <flux:icon.arrow-right
                                variant="micro"
                                class="text-neutral-400 transition group-hover:translate-x-0.5"
                            />
                        </a>
                    @endforeach
                </div>

                <p class="mt-8 text-sm text-neutral-500">
                    {{ __('To see a real 404 instead of a preview, visit any address that does not exist.') }}
                </p>
            </main>
        </div>
    </div>

    @fluxScripts
</body>
</html>
