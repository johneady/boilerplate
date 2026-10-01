@props(['tour'])

{{--
    One tour in a grid: photo, style, destination, length, next date and the
    "from" price. Expects destination and bookableDepartures eager-loaded.
--}}
@php
    $nextDeparture = $tour->relationLoaded('bookableDepartures') ? $tour->bookableDepartures->first() : null;
@endphp

<article {{ $attributes->class('group relative flex flex-col overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-neutral-800 dark:bg-neutral-900') }}>
    <div class="relative aspect-[4/3] overflow-hidden bg-neutral-200 dark:bg-neutral-800">
        @if ($tour->imageUrl() !== null)
            <img
                src="{{ $tour->imageUrl() }}"
                alt=""
                loading="lazy"
                class="size-full object-cover transition duration-500 group-hover:scale-105"
            />
        @endif

        <span class="absolute top-3 left-3 inline-flex items-center gap-1 rounded-full bg-white/90 px-2.5 py-1 text-xs font-semibold text-neutral-800 backdrop-blur">
            <flux:icon :icon="$tour->style->icon()" variant="micro" class="text-teal-700" />
            {{ __($tour->style->label()) }}
        </span>

        @if ($tour->is_featured)
            <span class="absolute top-3 right-3 rounded-full bg-orange-500 px-2.5 py-1 text-xs font-semibold text-white">
                {{ __('Bestseller') }}
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-5">
        <p class="text-xs font-semibold tracking-wide text-teal-700 uppercase dark:text-teal-400">
            {{ $tour->destination->place() }}
        </p>

        <h3 class="mt-1 font-display text-xl leading-snug font-semibold">
            <a href="{{ route('tours.show', $tour) }}" wire:navigate class="after:absolute after:inset-0">
                {{ $tour->name }}
            </a>
        </h3>

        <p class="mt-2 line-clamp-2 text-sm text-neutral-600 dark:text-neutral-400">{{ $tour->summary }}</p>

        <dl class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm text-neutral-600 dark:text-neutral-400">
            <div class="flex items-center gap-1">
                <dt class="sr-only">{{ __('Duration') }}</dt>
                <flux:icon.clock variant="micro" />
                <dd>{{ trans_choice(':count day|:count days', $tour->duration_days) }}</dd>
            </div>
            <div class="flex items-center gap-1">
                <dt class="sr-only">{{ __('Group size') }}</dt>
                <flux:icon.user-group variant="micro" />
                <dd>{{ __('Max :count', ['count' => $tour->group_size_max]) }}</dd>
            </div>
            @if ($nextDeparture !== null)
                <div class="flex items-center gap-1">
                    <dt class="sr-only">{{ __('Next departure') }}</dt>
                    <flux:icon.calendar variant="micro" />
                    <dd>{{ $nextDeparture->starts_on->format('j M Y') }}</dd>
                </div>
            @endif
        </dl>

        <div class="mt-auto flex items-end justify-between gap-3 pt-5">
            <p class="text-sm text-neutral-500">
                {{ __('From') }}
                <span class="block text-2xl font-semibold text-neutral-900 dark:text-white">{{ $tour->formattedPrice() }}</span>
                <span class="text-xs">{{ __('per person') }}</span>
            </p>

            <span class="inline-flex items-center gap-1 text-sm font-semibold text-teal-700 dark:text-teal-400">
                {{ __('View tour') }}
                <flux:icon.arrow-right variant="micro" class="transition group-hover:translate-x-0.5" />
            </span>
        </div>
    </div>
</article>
