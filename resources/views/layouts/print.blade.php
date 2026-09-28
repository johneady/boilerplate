{{--
    The shell for the customer-facing phone flow: the QR landing and the
    website's "send us your photos" page share it.

    Deliberately its own layout rather than public.blade.php: this is an app
    screen a customer holds in one hand, not a marketing page -- no footer, no
    navigation, nothing to tap that leaves the flow. The brand header carries
    the counter's name when one was scanned, so the whole experience stays the
    one the QR code promised.
--}}
@php
    $title = filled($title ?? null) ? $title : __('Send us your photos');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', array_filter(['title' => $title]))
</head>
<body class="antialiased">
    <div class="relative min-h-dvh overflow-hidden bg-white text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <div
            class="pointer-events-none absolute -top-40 -right-32 h-[30rem] w-[30rem] rounded-full bg-linear-to-br from-teal-300/25 via-cyan-300/10 to-transparent blur-3xl"
            aria-hidden="true"
        ></div>
        <div
            class="pointer-events-none absolute -bottom-48 -left-40 h-[26rem] w-[26rem] rounded-full bg-linear-to-tr from-sky-300/15 via-teal-300/10 to-transparent blur-3xl"
            aria-hidden="true"
        ></div>

        <div class="relative mx-auto flex min-h-dvh w-full max-w-xl flex-col px-5 pt-6 pb-28 sm:pb-10">
            <header class="flex items-center justify-between gap-3 pb-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-medium" wire:navigate>
                    <x-app-logo-icon class="size-8" />
                    <span>{{ $businessName }}</span>
                </a>

                @isset($locationName)
                    <flux:badge color="teal" size="sm" icon="map-pin" inset="top bottom">
                        {{ $locationName }}
                    </flux:badge>
                @endisset
            </header>

            {{ $slot }}
        </div>
    </div>
    @fluxScripts
</body>
</html>
