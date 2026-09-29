<main class="flex-1 py-10">
    <div class="max-w-2xl">
        <h1 class="font-display text-5xl font-semibold tracking-tight">{{ __('Browse perfumes') }}</h1>
        <p class="mt-3 text-neutral-600 dark:text-neutral-400">
            {{ __('Search by name, house, perfumer or a note you love, such as vanilla, iris or oud.') }}
        </p>
    </div>

    <div class="mt-8 grid gap-3 rounded-2xl border border-plum-100 bg-white/70 p-4 sm:grid-cols-2 lg:grid-cols-5 dark:border-plum-900/60 dark:bg-plum-950/30">
        <div class="sm:col-span-2">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search perfumes or notes…')"
                :aria-label="__('Search')"
                clearable
            />
        </div>

        <flux:select wire:model.live="family" :aria-label="__('Family')">
            <flux:select.option value="">{{ __('All families') }}</flux:select.option>
            @foreach ($families as $option)
                <flux:select.option :value="$option->value">{{ __($option->label()) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="brand" :aria-label="__('House')">
            <flux:select.option value="">{{ __('All houses') }}</flux:select.option>
            @foreach ($brands as $option)
                <flux:select.option :value="$option->slug">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex gap-3">
            <flux:select wire:model.live="gender" :aria-label="__('Audience')">
                <flux:select.option value="">{{ __('Anyone') }}</flux:select.option>
                @foreach ($audiences as $option)
                    <flux:select.option :value="$option->value">{{ __($option->label()) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-neutral-600 dark:text-neutral-400" data-test="result-count">
            {{ trans_choice(':count perfume|:count perfumes', $this->perfumes->total(), ['count' => number_format($this->perfumes->total())]) }}
            @if ($search !== '' || $family !== '' || $brand !== '' || $gender !== '')
                · <button type="button" wire:click="clearFilters" class="font-medium text-plum-700 hover:underline dark:text-plum-300">{{ __('Clear filters') }}</button>
            @endif
        </p>

        <div class="w-48">
            <flux:select wire:model.live="sort" size="sm" :aria-label="__('Sort')">
                <flux:select.option value="popular">{{ __('Most followed') }}</flux:select.option>
                <flux:select.option value="newest">{{ __('Newest release') }}</flux:select.option>
                <flux:select.option value="oldest">{{ __('Oldest release') }}</flux:select.option>
                <flux:select.option value="name">{{ __('A to Z') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-60">
        @forelse ($this->perfumes as $perfume)
            <x-perfumes.card :perfume="$perfume" wire:key="perfume-{{ $perfume->id }}" />
        @empty
            <div class="rounded-2xl border border-dashed border-plum-200 p-10 text-center text-neutral-600 sm:col-span-2 lg:col-span-3 dark:border-plum-800 dark:text-neutral-400">
                {{ __('No perfumes match those filters yet.') }}
            </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $this->perfumes->links() }}
    </div>
</main>
