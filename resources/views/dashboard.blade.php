<x-layouts::app :title="__('Dashboard')">
    {{--
        The landing page for ordinary signed-in users, NOT an admin screen --
        administration lives in the Filament panel at /admin. Everything linked
        from here is something any authenticated user may do to their own
        account, so nothing on this page is gated on is_admin.
    --}}
    @php
        /**
         * Everything linked from here is something an ordinary authenticated
         * user may do to their OWN account.
         *
         * Declared in the view rather than passed in from a provider: the
         * gradient utilities below are Tailwind class names, and keeping them
         * under resources/views means they sit inside the @source path in
         * resources/css/app.css rather than relying on Tailwind's project-root
         * auto-detection to reach into app/.
         */
        $quickLinks = [
            [
                'title' => __('Profile'),
                'description' => __('Update your name and email address.'),
                'url' => route('profile.edit'),
                'icon' => 'user-circle',
                'tint' => 'from-sky-500 to-indigo-500',
            ],
            [
                'title' => __('Security'),
                'description' => __('Two-factor authentication, passkeys and password.'),
                'url' => route('security.edit'),
                'icon' => 'shield-check',
                'tint' => 'from-emerald-500 to-teal-500',
            ],
            [
                'title' => __('Appearance'),
                'description' => __('Switch between light, dark and system themes.'),
                'url' => route('appearance.edit'),
                'icon' => 'swatch',
                'tint' => 'from-amber-500 to-pink-500',
            ],
        ];
    @endphp

    <div class="flex w-full flex-col gap-6">
        <div class="relative overflow-hidden rounded-xl bg-linear-to-br from-teal-700 via-teal-600 to-cyan-600 p-6 text-white sm:p-8 dark:from-teal-800 dark:via-teal-700 dark:to-cyan-700">
            {{-- Decorative only: aria-hidden so the gradient blobs are not announced. --}}
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -top-16 -right-16 size-56 rounded-full bg-white/15 blur-2xl"
            ></div>
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-20 -left-10 size-56 rounded-full bg-white/10 blur-2xl"
            ></div>

            <div class="relative">
                {{-- A plain pill rather than flux:badge: the badge's own light
                     palette washes out against the gradient behind it. --}}
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-1 text-xs font-medium text-white ring-1 ring-white/30 backdrop-blur-sm ring-inset">
                    <span class="size-1.5 rounded-full bg-lime-300"></span>
                    {{ __('Signed in') }}
                </span>

                <flux:heading size="xl" level="1" class="mt-3 text-white!">
                    {{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}
                </flux:heading>

                <flux:text class="mt-2 max-w-prose text-white/80!">
                    {{ __('This is your :business account: your trip requests and bookings, plus your details and security.', ['business' => $businessName]) }}
                </flux:text>
            </div>
        </div>

        {{-- The traveller's own booking requests, matched by account or by the
             address they used before signing up. --}}
        @php
            $tripRequests = \App\Models\TripInquiry::query()
                ->belongingTo(auth()->user())
                ->with(['tour.destination', 'departure.tour', 'destination'])
                ->latest()
                ->get();
        @endphp

        <section aria-labelledby="my-trips">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading id="my-trips" size="lg">{{ __('My trips') }}</flux:heading>
                <flux:button :href="route('tours.index')" size="sm" icon="magnifying-glass">{{ __('Find another tour') }}</flux:button>
            </div>

            @if ($tripRequests->isEmpty())
                <div class="mt-4 rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                    <flux:text>{{ __('No trip requests yet. Pick a tour and press "Request to book" to see it here.') }}</flux:text>
                </div>
            @else
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    @foreach ($tripRequests as $trip)
                        <article class="flex gap-4 overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="my-trip">
                            @php($photo = $trip->tour?->imageUrl() ?? $trip->destination?->imageUrl())
                            @if ($photo !== null)
                                <img src="{{ $photo }}" alt="" class="size-24 shrink-0 rounded-lg object-cover" />
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:badge size="sm" :color="match ($trip->status) {
                                        \App\Travel\InquiryStatus::New => 'amber',
                                        \App\Travel\InquiryStatus::Contacted => 'sky',
                                        \App\Travel\InquiryStatus::Confirmed => 'green',
                                        \App\Travel\InquiryStatus::Declined => 'zinc',
                                    }">{{ __($trip->status->label()) }}</flux:badge>
                                    <span class="font-mono text-xs text-zinc-500">{{ $trip->reference }}</span>
                                </div>
                                <flux:heading class="mt-1.5 truncate">{{ $trip->tripLabel() }}</flux:heading>
                                <flux:text size="sm" class="mt-0.5">
                                    {{ $trip->departure?->dateRange() ?? $trip->travel_month ?? __('Dates to be agreed') }}
                                    · {{ trans_choice(':count traveller|:count travellers', $trip->travellers()) }}
                                    @if ($trip->formattedQuote() !== null)
                                        · {{ $trip->formattedQuote() }}
                                    @endif
                                </flux:text>
                                @if ($trip->tour !== null)
                                    <flux:link :href="route('tours.show', $trip->tour)" class="mt-2 inline-block text-sm" wire:navigate>{{ __('View tour') }}</flux:link>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($quickLinks as $link)
                <a
                    href="{{ $link['url'] }}"
                    wire:navigate
                    class="group relative flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600"
                >
                    <span class="flex size-10 items-center justify-center rounded-lg bg-linear-to-br {{ $link['tint'] }} text-white">
                        <flux:icon :icon="$link['icon']" variant="mini" />
                    </span>

                    <span class="flex-1">
                        <flux:heading size="lg" class="flex items-center gap-1">
                            {{ $link['title'] }}
                            <flux:icon.arrow-right
                                variant="micro"
                                class="opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-60"
                            />
                        </flux:heading>

                        <flux:text class="mt-1">{{ $link['description'] }}</flux:text>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</x-layouts::app>
