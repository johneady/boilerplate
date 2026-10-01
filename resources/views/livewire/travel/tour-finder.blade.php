<main class="flex flex-1 flex-col">
    <section class="border-b border-neutral-200 bg-stone-50 dark:border-neutral-800 dark:bg-neutral-900/50">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
            <p class="text-sm font-semibold tracking-widest text-teal-700 uppercase dark:text-teal-400">{{ __('Tours & packages') }}</p>
            <h1 class="mt-2 font-display text-4xl font-semibold sm:text-5xl">{{ __('Find your tour') }}</h1>
            <p class="mt-4 max-w-2xl text-lg text-neutral-600 dark:text-neutral-400">
                {{ __('Guided small-group departures with hotels, most meals and a local expert included. Prices are per person, twin share.') }}
            </p>

            <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <flux:select wire:model.live="destination" :label="__('Destination')">
                    <flux:select.option value="">{{ __('Anywhere') }}</flux:select.option>
                    @foreach ($this->destinations as $option)
                        <flux:select.option :value="$option->slug">{{ $option->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="style" :label="__('Travel style')">
                    <flux:select.option value="">{{ __('Any style') }}</flux:select.option>
                    @foreach (\App\Travel\TourStyle::cases() as $option)
                        <flux:select.option :value="$option->value">{{ __($option->label()) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="duration" :label="__('Trip length')">
                    <flux:select.option value="">{{ __('Any length') }}</flux:select.option>
                    <flux:select.option value="short">{{ __('Up to 6 days') }}</flux:select.option>
                    <flux:select.option value="week">{{ __('7 to 10 days') }}</flux:select.option>
                    <flux:select.option value="long">{{ __('11 days or more') }}</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="sort" :label="__('Sort by')">
                    @foreach (\App\Livewire\Travel\TourFinder::SORTS as $value => $label)
                        <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </section>

    <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-neutral-600 dark:text-neutral-400" data-test="tour-count">
                {{ trans_choice(':count tour found|:count tours found', $this->tours->count()) }}
            </p>

            @if ($this->hasFilters())
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
            @endif
        </div>

        <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-60">
            @forelse ($this->tours as $tour)
                <x-travel.tour-card :tour="$tour" wire:key="tour-{{ $tour->id }}" />
            @empty
                <div class="rounded-2xl border border-dashed border-neutral-300 p-10 text-center sm:col-span-2 lg:col-span-3 dark:border-neutral-700">
                    <p class="font-display text-xl font-semibold">{{ __('No scheduled tours match those filters') }}</p>
                    <p class="mt-2 text-neutral-600 dark:text-neutral-400">{{ __('We can still build it for you: tell us what you have in mind.') }}</p>
                    <div class="mt-6 flex justify-center gap-3">
                        <flux:button wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
                        <flux:button :href="route('plan-trip')" variant="primary" wire:navigate>{{ __('Plan a tailor-made trip') }}</flux:button>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</main>
