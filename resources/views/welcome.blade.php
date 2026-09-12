<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="antialiased">
    <div class="relative min-h-dvh overflow-hidden bg-white text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <div
            class="pointer-events-none absolute -top-40 -right-32 h-[36rem] w-[36rem] rounded-full bg-linear-to-br from-sky-400/20 via-indigo-400/10 to-transparent blur-3xl"
            aria-hidden="true"
        ></div>
        <div
            class="pointer-events-none absolute -bottom-48 -left-40 h-[32rem] w-[32rem] rounded-full bg-linear-to-tr from-violet-400/20 via-sky-400/10 to-transparent blur-3xl"
            aria-hidden="true"
        ></div>

        <div class="relative mx-auto flex min-h-dvh w-full max-w-6xl flex-col px-6 lg:px-8">
            <header class="flex items-center justify-between py-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-medium" wire:navigate>
                    <x-app-logo-icon class="size-7" />
                    <span>{{ $businessName }}</span>
                </a>

                @if (Route::has('login'))
                    <nav class="flex items-center gap-2">
                        @auth
                            @if (auth()->user()->is_admin)
                                <flux:button
                                    :href="filament()->getPanel('admin')->getUrl()"
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
                                        {{ __('Sign up') }}
                                    </flux:button>
                                @endif
                            @endregistrationEnabled
                        @endauth
                    </nav>
                @endif
            </header>

            <main class="flex flex-1 flex-col justify-center py-16">
                <div class="max-w-3xl">
                    <flux:badge size="sm" color="sky" inset="top bottom">{{ __('Est. whenever') }}</flux:badge>

                    <h1 class="mt-6 text-5xl font-semibold tracking-tight text-balance sm:text-6xl">
                        {{ __('We make the thing that holds the other things.') }}
                    </h1>

                    <p class="mt-6 max-w-2xl text-lg leading-relaxed text-neutral-600 dark:text-neutral-400">
                        {{
                            __('Since the beginning, :business has specialised in the load-bearing middle
                            — the quiet layer nobody photographs and everybody depends on. Our output is measured in
                            afternoons not spent rewriting the same login page.', ['business' => $businessName])
                        }}
                    </p>

                    <div class="mt-10 flex flex-wrap items-center gap-3">
                        <flux:button :href="route('login')" variant="primary" wire:navigate>
                            {{ __('Get started') }}
                        </flux:button>
                        <flux:button href="#principles" variant="ghost"> {{ __('Read the brochure') }} </flux:button>
                    </div>
                </div>

                <div id="principles" class="mt-24 grid gap-8 sm:grid-cols-3">
                    @foreach ([
                        ['heading' => __('Structurally sound'), 'body' => __('Every beam accounted for, including the ones holding up the beams. Independently verified by people who enjoy that sort of thing.')],
                        ['heading' => __('Quietly durable'), 'body' => __('Built to be ignored for years at a time. The highest compliment our work receives is no compliment at all.')],
                        ['heading' => __('Sensibly finished'), 'body' => __('Sanded where it matters, left honest where it does not. We stop before the point of diminishing returns, on principle.')],
                    ] as $principle)
                        <div class="rounded-xl border border-neutral-200 bg-white/60 p-6 dark:border-neutral-800 dark:bg-neutral-900/40">
                            <flux:heading size="lg">{{ $principle['heading'] }}</flux:heading>
                            <p class="mt-2 text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">
                                {{ $principle['body'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </main>

            <x-business-footer class="border-t border-neutral-200 py-8 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400" />
        </div>
    </div>
    @fluxScripts
</body>
</html>
