{{--
    The storefront listing. The layout is supplied by the component's render(),
    which names the public shell -- see App\Livewire\Shop\Catalogue.
--}}
<main class="flex-1 py-12">
    <header class="max-w-3xl">
        <flux:badge size="sm" color="sky" inset="top bottom">{{ __('Licensed aerial footage') }}</flux:badge>

        <h1 class="mt-4 text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
            {{ __('Drone footage packages') }}
        </h1>

        <p class="mt-4 text-lg leading-relaxed text-neutral-600 dark:text-neutral-400">
            {{ __('Every package is filmed at a single location and delivered as a ready-to-edit set of clips, with a royalty-free commercial licence.') }}
        </p>
    </header>

    <div class="mt-10 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('Filter by region') }}">
            <flux:button
                size="sm"
                :variant="$region === '' ? 'primary' : 'outline'"
                wire:click="$set('region', '')"
            >
                {{ __('All regions') }}
            </flux:button>

            @foreach ($this->regions as $option)
                <flux:button
                    size="sm"
                    wire:key="region-{{ $option->value }}"
                    :variant="$region === $option->value ? 'primary' : 'outline'"
                    wire:click="$set('region', '{{ $option->value }}')"
                >
                    {{ __($option->label()) }}
                </flux:button>
            @endforeach
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search locations...')"
                :aria-label="__('Search locations')"
                clearable
                class="sm:w-64"
            />

            <flux:select wire:model.live="sort" :aria-label="__('Sort packages')" class="sm:w-48">
                <flux:select.option value="featured">{{ __('Featured') }}</flux:select.option>
                <flux:select.option value="price-asc">{{ __('Price: low to high') }}</flux:select.option>
                <flux:select.option value="price-desc">{{ __('Price: high to low') }}</flux:select.option>
                <flux:select.option value="newest">{{ __('Newest') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    <p class="mt-6 text-sm text-neutral-500 dark:text-neutral-400" aria-live="polite">
        {{ trans_choice(':count package|:count packages', $this->packages->count(), ['count' => $this->packages->count()]) }}
    </p>

    @if ($this->packages->isEmpty())
        <div class="mt-6 rounded-2xl border border-dashed border-neutral-300 px-6 py-16 text-center dark:border-neutral-700">
            <flux:icon.map class="mx-auto size-10 text-neutral-400" />
            <flux:heading size="lg" class="mt-4">{{ __('No packages match your search') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Try another location, or clear the filters to see everything.') }}</flux:text>
            <flux:button class="mt-6" size="sm" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
        </div>
    @else
        <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-60">
            @foreach ($this->packages as $package)
                <x-shop.package-card :package="$package" wire:key="catalogue-{{ $package->id }}" />
            @endforeach
        </div>
    @endif
</main>
