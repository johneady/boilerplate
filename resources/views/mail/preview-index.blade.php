{{--
    The index for the local email preview, kept deliberately close to
    errors/preview-index.blade.php so the two dev previews read as a pair.

    Each email opens in a modal containing an iframe pointed at its own route,
    rather than navigating away: an email carries its own complete document
    with inlined styles, so dropping the markup straight into this page would
    let the two stylesheets fight. The iframe keeps it in a separate document
    while the modal keeps the list in view, so comparing two emails no longer
    means a round trip through the back button.

    The iframes are lazy: src is only assigned when a modal opens, so listing
    four emails does not render four of them up front. The route stays
    directly reachable, which is what the tests drive and what a hard reload
    after a template change still uses.

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
                        @php
                            $label = Str::ucfirst($email['description']);
                            $url = route('dev.mails.show', $slug);
                        @endphp

                        {{--
                            Opened through flux:modal.trigger rather than by
                            dispatching an event by hand: Flux listens for
                            "modal-show" with a { name } payload, so the
                            "open-modal" convention carried over from the
                            Livewire starter kit silently does nothing here.

                            The row stays a real anchor inside the trigger, so
                            middle-click and "open in new tab" still reach the
                            route; the trigger's own click handler takes over
                            the plain click, and preventDefault stops the
                            navigation that would otherwise race it.
                        --}}
                        <flux:modal.trigger :name="$slug">
                            <a
                                href="{{ $url }}"
                                x-on:click.prevent
                                class="group flex cursor-pointer items-center gap-3 rounded-xl border border-zinc-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-lg"
                            >
                                <span class="flex-1 text-sm font-medium text-neutral-900">
                                    {{ $label }}
                                </span>
                                <flux:icon.arrow-right
                                    variant="micro"
                                    class="text-neutral-400 transition group-hover:translate-x-0.5"
                                />
                            </a>
                        </flux:modal.trigger>

                        {{--
                            variant="bare" because the email brings its own
                            chrome: Flux's padded white panel around a full
                            email document would read as a frame within a frame.
                        --}}
                        <flux:modal
                            :name="$slug"
                            variant="bare"
                            class="w-full max-w-4xl"
                            x-data="{ src: null }"
                            x-on:modal-show.document="if ($event.detail?.name === '{{ $slug }}') src ??= '{{ $url }}'"
                        >
                            <div class="overflow-hidden rounded-xl bg-white shadow-xl">
                                <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-4 py-3">
                                    <p class="text-sm font-medium text-neutral-900">{{ $label }}</p>

                                    <div class="flex items-center gap-1">
                                        <flux:button
                                            :href="$url"
                                            target="_blank"
                                            size="sm"
                                            variant="subtle"
                                            icon="arrow-top-right-on-square"
                                        >
                                            {{ __('Open') }}
                                        </flux:button>

                                        <flux:modal.close>
                                            <flux:button size="sm" variant="subtle" icon="x-mark" inset="top bottom" />
                                        </flux:modal.close>
                                    </div>
                                </div>

                                {{--
                                    Fixed height rather than auto: an iframe
                                    cannot size itself to a cross-document body,
                                    and measuring it would need same-origin
                                    scripting for no real gain here.
                                --}}
                                <iframe
                                    x-bind:src="src"
                                    title="{{ $label }}"
                                    class="h-[70dvh] w-full border-0 bg-white"
                                ></iframe>
                            </div>
                        </flux:modal>
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
