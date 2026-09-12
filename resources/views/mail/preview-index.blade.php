{{--
    The index for the local email preview, kept deliberately close to
    errors/preview-index.blade.php so the two dev previews read as a pair.

    The emails themselves render in an iframe on their own route rather than
    inline: an email carries its own full document with inlined styles, and
    dropping that into this page would let the two stylesheets fight.

    Brands from $businessName, NOT config('app.name'). The error preview index
    this is otherwise modelled on uses app.name because its pages must render
    with the database down (.ai/rules/errors.md); that exemption does not reach
    here, and the emails listed below are themselves branded from the setting,
    so an app.name heading would disagree with every message under it.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <title>{{ __('Email preview') }} - {{ $businessName }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-linear-to-b from-white to-neutral-50 antialiased">
    <div class="relative flex min-h-dvh flex-col overflow-hidden">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -top-40 -right-32 h-[36rem] w-[36rem] rounded-full bg-linear-to-br from-sky-300/30 via-indigo-300/20 to-transparent blur-3xl"
        ></div>

        <div class="relative mx-auto flex w-full max-w-2xl flex-1 flex-col px-6 lg:px-8">
            <header class="pt-8 pb-4">
                <a href="/" class="inline-flex items-center gap-2 font-medium text-neutral-900">
                    <x-app-logo-icon class="size-7" />
                    <span>{{ $businessName }}</span>
                </a>
            </header>

            <main class="flex flex-1 flex-col pt-2 pb-12">
                <div class="flex">
                    <flux:badge size="sm" color="amber" inset="top bottom">{{ __('Local preview') }}</flux:badge>
                </div>

                <h1 class="mt-4 text-4xl font-semibold tracking-tight text-neutral-900 sm:text-5xl">
                    {{ __('Emails') }}
                </h1>

                <p class="mt-4 max-w-prose text-lg leading-relaxed text-neutral-600">
                    {{ __('Every email the application can send, rendered as a recipient would receive it. Nothing is delivered: these are rendered in place, so a template change only needs a reload.') }}
                </p>

                <div class="mt-10 grid gap-3">
                    @foreach ($emails as $slug => $email)
                        <a
                            href="{{ route('dev.mails.show', $slug) }}"
                            class="group flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-lg"
                        >
                            <span class="flex-1 text-sm font-medium text-neutral-900">
                                {{ Str::ucfirst($email['description']) }}
                            </span>
                            <flux:icon.arrow-right
                                variant="micro"
                                class="text-neutral-400 transition group-hover:translate-x-0.5"
                            />
                        </a>
                    @endforeach
                </div>

                <p class="mt-8 text-sm text-neutral-500">
                    {{ __('To check delivery through a real mailer instead, run php artisan app:preview-mails against a catch-all inbox such as Mailpit.') }}
                </p>
            </main>
        </div>
    </div>

    @fluxScripts
</body>
</html>
