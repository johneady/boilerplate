@props(['item'])

{{--
    One item on the menu: photo, name, dietary notes, price and the notice it
    needs, with a link that opens the order form with this item already chosen.
--}}
<article
    {{ $attributes->class('group flex flex-col overflow-hidden rounded-2xl border border-amber-200/70 bg-white/80 shadow-sm shadow-amber-900/5 transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-amber-900/10 dark:border-white/10 dark:bg-stone-900/70') }}
    data-test="menu-item"
>
    <div class="relative aspect-[4/3] overflow-hidden bg-amber-100 dark:bg-stone-800">
        @if ($item->imageUrl() !== null)
            <img
                src="{{ $item->imageUrl() }}"
                alt="{{ $item->name }}"
                loading="lazy"
                class="size-full object-cover transition duration-500 group-hover:scale-105"
            />
        @endif

        <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
            @if ($item->is_seasonal)
                <span class="rounded-full bg-orange-600/90 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur">{{ __('Seasonal') }}</span>
            @endif
            @if ($item->is_featured)
                <span class="rounded-full bg-stone-900/80 px-2.5 py-1 text-xs font-semibold text-amber-100 backdrop-blur">{{ __('Customer favourite') }}</span>
            @endif
        </div>
    </div>

    <div class="flex flex-1 flex-col p-5">
        <div class="flex items-start justify-between gap-3">
            <h3 class="font-display text-xl leading-snug font-semibold">{{ $item->name }}</h3>
            <p class="shrink-0 text-right">
                <span class="block text-lg font-semibold text-amber-800 dark:text-amber-300">{{ $item->formattedPrice() }}</span>
                <span class="block text-xs text-stone-500 dark:text-stone-400">{{ $item->price_unit }}</span>
            </p>
        </div>

        <p class="mt-2 text-sm leading-relaxed text-stone-600 dark:text-stone-400">{{ $item->description }}</p>

        @if ($item->dietaryTags() !== [])
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($item->dietaryTags() as $tag)
                    <flux:badge size="sm" :color="$tag->color()">{{ __($tag->label()) }}</flux:badge>
                @endforeach
            </div>
        @endif

        <div class="mt-auto flex items-end justify-between gap-3 pt-5">
            <p class="text-xs text-stone-500 dark:text-stone-400">
                @if (filled($item->serves))
                    <span class="block">{{ __('Serves :count', ['count' => $item->serves]) }}</span>
                @endif
                <span class="block">{{ trans_choice(':count day notice|:count days notice', $item->notice_days, ['count' => $item->notice_days]) }}</span>
            </p>

            <a
                href="{{ route('order', ['item' => $item->slug]) }}"
                class="inline-flex items-center gap-1 rounded-full border border-amber-300 px-3.5 py-1.5 text-sm font-semibold text-amber-900 transition group-hover:border-amber-700 group-hover:bg-amber-700 group-hover:text-white dark:border-amber-500/40 dark:text-amber-200 dark:group-hover:bg-amber-500 dark:group-hover:text-stone-950"
                wire:navigate
            >
                {{ __('Order this') }}
                <flux:icon.arrow-right variant="micro" />
            </a>
        </div>
    </div>
</article>
