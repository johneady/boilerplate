<x-layouts::public :title="__('Plan a tailor-made trip')" :description="__('Tell us where, when and who is coming, and a trip specialist will design a private itinerary for you.')">
    <main class="flex flex-1 flex-col">
        <div class="mx-auto grid w-full max-w-7xl gap-12 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-[1fr_28rem] lg:px-8">
            <div>
                <p class="text-sm font-semibold tracking-widest text-teal-700 uppercase dark:text-teal-400">{{ __('Tailor-made') }}</p>
                <h1 class="mt-2 font-display text-4xl font-semibold sm:text-5xl">{{ __('Your trip, designed around you') }}</h1>
                <p class="mt-4 max-w-xl text-lg text-neutral-600 dark:text-neutral-400">
                    {{ __('Honeymoons, milestone birthdays, multi-generation family trips or simply your own pace: tell us the idea and we\'ll do the rest.') }}
                </p>

                <ol class="mt-10 space-y-6">
                    @foreach ([
                        ['title' => __('Share your idea'), 'body' => __('Where, when, who\'s coming and what you love. Two minutes, no commitment.')],
                        ['title' => __('Get your itinerary'), 'body' => __('A specialist sends a day-by-day plan and a clear price within two business days.')],
                        ['title' => __('Fine-tune and travel'), 'body' => __('Adjust anything you like. Secure it with a 20% deposit once it\'s perfect.')],
                    ] as $step)
                        <li class="flex gap-4">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-teal-700 font-semibold text-white">{{ $loop->iteration }}</span>
                            <div>
                                <p class="font-semibold">{{ $step['title'] }}</p>
                                <p class="mt-1 text-neutral-600 dark:text-neutral-400">{{ $step['body'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <livewire:travel.trip-inquiry-form :destination="request()->string('destination')->toString() ?: null" />
        </div>
    </main>
</x-layouts::public>
