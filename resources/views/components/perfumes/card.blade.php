@props([
    'perfume',
])

{{--
    One perfume in a grid: the house, the name, the family swatch and the
    headline notes. The follower count is shown when the query loaded it.
--}}
<a
    href="{{ route('perfumes.show', $perfume) }}"
    wire:navigate
    {{ $attributes->class('group flex h-full flex-col rounded-2xl border border-plum-100 bg-white/80 p-5 transition hover:-translate-y-0.5 hover:border-plum-300 hover:shadow-lg hover:shadow-plum-900/5 dark:border-plum-900/60 dark:bg-plum-950/40 dark:hover:border-plum-700') }}
>
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-semibold tracking-widest text-plum-600 uppercase dark:text-plum-300">
            {{ $perfume->brand->name }}
        </p>

        @if ($perfume->family !== null)
            <span class="inline-flex shrink-0 items-center gap-1.5 text-xs text-neutral-600 dark:text-neutral-400">
                <span class="size-2 rounded-full" style="background-color: {{ $perfume->family->color() }}"></span>
                {{ __($perfume->family->label()) }}
            </span>
        @endif
    </div>

    <h3 class="mt-2 font-display text-2xl leading-tight font-semibold text-neutral-900 group-hover:text-plum-700 dark:text-white dark:group-hover:text-plum-200">
        {{ $perfume->name }}
    </h3>

    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
        {{ collect([$perfume->concentration?->label() ? __($perfume->concentration->label()) : null, $perfume->release_year])->filter()->implode(' · ') }}
    </p>

    <p class="mt-3 line-clamp-2 flex-1 text-sm text-neutral-600 dark:text-neutral-300">
        {{ implode(', ', array_slice($perfume->allNotes(), 0, 5)) }}
    </p>

    @isset($perfume->followers_count)
        <p class="mt-4 flex items-center gap-1.5 text-xs font-medium text-neutral-500 dark:text-neutral-400">
            <flux:icon.heart variant="micro" class="text-plum-500" />
            {{ trans_choice(':count follower|:count followers', $perfume->followers_count, ['count' => number_format($perfume->followers_count)]) }}
        </p>
    @endisset
</a>
