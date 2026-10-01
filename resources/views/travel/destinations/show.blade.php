<x-layouts::public :title="$destination->place()" :description="$destination->tagline">
    <main class="flex flex-1 flex-col">
        <section class="relative isolate overflow-hidden bg-neutral-900">
            @if ($destination->imageUrl() !== null)
                <img src="{{ $destination->imageUrl() }}" alt="" class="absolute inset-0 -z-10 size-full object-cover" fetchpriority="high" />
            @endif
            <div class="absolute inset-0 -z-10 bg-linear-to-t from-black/80 via-black/40 to-black/10"></div>

            <div class="mx-auto max-w-7xl px-4 pt-32 pb-12 text-white sm:px-6 sm:pt-48 lg:px-8">
                <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-white/80">
                    <a href="{{ route('destinations.index') }}" class="hover:text-white" wire:navigate>{{ __('Destinations') }}</a>
                    <span aria-hidden="true">/</span>
                    <span>{{ __($destination->region->label()) }}</span>
                </nav>
                <h1 class="mt-3 font-display text-4xl font-semibold sm:text-6xl">{{ $destination->name }}</h1>
                <p class="mt-2 text-lg text-white/85">{{ $destination->country }} · {{ $destination->tagline }}</p>
            </div>
        </section>

        <div class="mx-auto grid w-full max-w-7xl gap-12 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-3 lg:px-8">
            <div class="lg:col-span-2">
                <h2 class="font-display text-2xl font-semibold">{{ __('About :destination', ['destination' => $destination->name]) }}</h2>
                <div class="mt-4 space-y-4 text-lg leading-relaxed text-neutral-700 dark:text-neutral-300">
                    @foreach (preg_split('/\R{2,}/', trim($destination->description)) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            </div>

            <aside class="space-y-4">
                @if (filled($destination->best_time))
                    <div class="rounded-2xl border border-neutral-200 p-5 dark:border-neutral-800">
                        <p class="flex items-center gap-2 font-semibold"><flux:icon.sun variant="mini" class="text-orange-500" /> {{ __('Best time to go') }}</p>
                        <p class="mt-2 text-neutral-600 dark:text-neutral-400">{{ $destination->best_time }}</p>
                    </div>
                @endif

                <div class="rounded-2xl bg-teal-900 p-5 text-white">
                    <p class="font-semibold">{{ __('Want :destination your way?', ['destination' => $destination->name]) }}</p>
                    <p class="mt-2 text-sm text-white/80">{{ __('Private departures, extra nights or a honeymoon upgrade: a specialist will tailor it for you.') }}</p>
                    <a href="{{ route('plan-trip', ['destination' => $destination->slug]) }}" wire:navigate class="mt-4 inline-flex items-center gap-1 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold hover:bg-orange-600">
                        {{ __('Plan a tailor-made trip') }}
                        <flux:icon.arrow-right variant="micro" />
                    </a>
                </div>

                @if ($destination->image_credit)
                    <p class="text-xs text-neutral-500">{{ $destination->image_credit }}</p>
                @endif
            </aside>
        </div>

        <section class="bg-stone-50 py-12 sm:py-16 dark:bg-neutral-900/50">
            <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="font-display text-3xl font-semibold">{{ __('Tours in :destination', ['destination' => $destination->name]) }}</h2>

                @if ($tours->isEmpty())
                    <p class="mt-6 text-neutral-600 dark:text-neutral-400">{{ __('New dates are coming soon. Ask us about a private trip in the meantime.') }}</p>
                @else
                    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($tours as $tour)
                            <x-travel.tour-card :tour="$tour" />
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        @if ($otherDestinations->isNotEmpty())
            <section class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
                <h2 class="font-display text-2xl font-semibold">{{ __('You might also love') }}</h2>
                <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-3">
                    @foreach ($otherDestinations as $other)
                        <x-travel.destination-card :destination="$other" class="aspect-[4/3]!" />
                    @endforeach
                </div>
            </section>
        @endif
    </main>
</x-layouts::public>
