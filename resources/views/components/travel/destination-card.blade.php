@props(['destination', 'size' => 'md'])

{{-- A destination as a full-bleed photo tile with its name over a gradient. --}}
<a
    href="{{ route('destinations.show', $destination) }}"
    wire:navigate
    {{ $attributes->class([
        'group relative block overflow-hidden rounded-2xl bg-neutral-800',
        'aspect-[3/4]' => $size === 'md',
        'aspect-[4/3] sm:aspect-auto sm:h-full' => $size === 'lg',
    ]) }}
>
    @if ($destination->imageUrl() !== null)
        <img
            src="{{ $destination->imageUrl() }}"
            alt=""
            loading="lazy"
            class="absolute inset-0 size-full object-cover transition duration-700 group-hover:scale-105"
        />
    @endif

    <div class="absolute inset-0 bg-linear-to-t from-black/80 via-black/20 to-transparent"></div>

    <div class="absolute inset-x-0 bottom-0 p-5 text-white">
        <p class="text-xs font-semibold tracking-widest text-white/80 uppercase">{{ $destination->country }}</p>
        <p class="mt-1 font-display text-2xl font-semibold">{{ $destination->name }}</p>
        <p class="mt-1 line-clamp-2 text-sm text-white/85">{{ $destination->tagline }}</p>

        @isset($destination->tours_count)
            <p class="mt-3 inline-flex items-center gap-1 text-sm font-semibold">
                {{ trans_choice(':count tour|:count tours', $destination->tours_count) }}
                @if (isset($destination->tours_min_price_per_person_cents))
                    · {{ __('from :price', ['price' => \App\Travel\Price::format((int) $destination->tours_min_price_per_person_cents)]) }}
                @endif
                <flux:icon.arrow-right variant="micro" class="transition group-hover:translate-x-0.5" />
            </p>
        @endisset
    </div>
</a>
