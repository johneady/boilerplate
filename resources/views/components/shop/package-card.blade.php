@props(['package'])

{{--
    One package in a grid: the catalogue, the home page's featured row and the
    "you may also like" row on a product page. The whole card is the link.
--}}
<a
    href="{{ route('shop.show', $package) }}"
    wire:navigate
    {{ $attributes->class('group flex flex-col overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 dark:border-neutral-800 dark:bg-neutral-900') }}
>
    <div class="relative aspect-16/10 overflow-hidden bg-neutral-100 dark:bg-neutral-800">
        @if ($package->imageUrl() !== null)
            <img
                src="{{ $package->imageUrl() }}"
                alt="{{ __('Aerial view of :location', ['location' => $package->location]) }}"
                loading="lazy"
                class="size-full object-cover transition duration-500 group-hover:scale-105"
            />
        @endif

        <div class="absolute inset-x-3 top-3 flex items-start justify-between gap-2">
            <span class="rounded-full bg-neutral-950/60 px-2.5 py-1 text-xs font-medium text-white backdrop-blur">
                {{ __($package->region->label()) }}
            </span>

            <x-shop.availability :package="$package" solid />
        </div>
    </div>

    <div class="flex flex-1 flex-col p-5">
        <p class="text-xs font-semibold tracking-wide text-sky-700 uppercase dark:text-sky-400">
            {{ $package->location }} · {{ $package->country }}
        </p>

        <h3 class="mt-1.5 text-lg font-semibold tracking-tight text-neutral-900 dark:text-white">
            {{ $package->title }}
        </h3>

        <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">
            {{ $package->summary }}
        </p>

        <dl class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-neutral-500 dark:text-neutral-400">
            <div class="flex items-center gap-1">
                <dt class="sr-only">{{ __('Resolution') }}</dt>
                <flux:icon.film variant="micro" />
                <dd>{{ $package->resolution }} · {{ $package->frame_rate }} fps</dd>
            </div>
            <div class="flex items-center gap-1">
                <dt class="sr-only">{{ __('Clips') }}</dt>
                <flux:icon.squares-2x2 variant="micro" />
                <dd>
                    {{ trans_choice(':count clip|:count clips', $package->clip_count, ['count' => $package->clip_count]) }}
                </dd>
            </div>
            <div class="flex items-center gap-1">
                <dt class="sr-only">{{ __('Running time') }}</dt>
                <flux:icon.clock variant="micro" />
                <dd>{{ $package->formattedDuration() }}</dd>
            </div>
        </dl>

        <div class="mt-auto flex items-end justify-between gap-4 pt-5">
            <p class="text-xl font-semibold text-neutral-900 dark:text-white">{{ $package->formattedPrice() }}</p>

            <span class="inline-flex items-center gap-1 text-sm font-medium text-sky-700 group-hover:underline dark:text-sky-400">
                {{ __('View package') }}
                <flux:icon.arrow-right variant="micro" />
            </span>
        </div>
    </div>
</a>
