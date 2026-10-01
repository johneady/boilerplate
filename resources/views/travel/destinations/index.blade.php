<x-layouts::public :title="__('Destinations')" :description="__('Every destination we run small-group tours to, grouped by region.')">
    <main class="flex flex-1 flex-col">
        <section class="border-b border-neutral-200 bg-stone-50 dark:border-neutral-800 dark:bg-neutral-900/50">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
                <p class="text-sm font-semibold tracking-widest text-teal-700 uppercase dark:text-teal-400">{{ __('Destinations') }}</p>
                <h1 class="mt-2 font-display text-4xl font-semibold sm:text-5xl">{{ __('Where we travel') }}</h1>
                <p class="mt-4 max-w-2xl text-lg text-neutral-600 dark:text-neutral-400">
                    {{ __('Places our guides know by heart. Choose one to see its tours, the best time to go and what\'s included.') }}
                </p>
            </div>
        </section>

        <div class="mx-auto w-full max-w-7xl space-y-14 px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
            @foreach ($regions as $region)
                @continue(! $destinationsByRegion->has($region->value))

                <section aria-labelledby="region-{{ $region->value }}">
                    <h2 id="region-{{ $region->value }}" class="font-display text-2xl font-semibold">{{ __($region->label()) }}</h2>
                    <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($destinationsByRegion[$region->value] as $destination)
                            <x-travel.destination-card :destination="$destination" class="aspect-[4/3]!" />
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </main>
</x-layouts::public>
