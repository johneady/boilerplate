@props([
    'image',
    'alt' => '',
    'eyebrow' => null,
    'title',
    'href' => null,
    'linkLabel' => null,
    'reverse' => false,
])

{{--
    A large photograph beside a short block of text and one link -- the
    building block of the home page's "why Voltiva" sections.
--}}
<section {{ $attributes->class('mx-auto grid max-w-360 items-center gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:gap-20 lg:px-10') }}>
    <x-voltiva.image :src="$image" :alt="$alt" ratio="aspect-4/3" :class="$reverse ? 'lg:order-2' : ''" />

    <div class="max-w-xl">
        @if (filled($eyebrow))
            <p class="text-volt-700 text-xs font-medium tracking-[0.2em] uppercase">{{ $eyebrow }}</p>
        @endif
        <h2 class="mt-3 text-3xl font-medium tracking-tight text-balance sm:text-4xl">{{ $title }}</h2>
        <div class="mt-5 space-y-4 text-lg leading-relaxed text-neutral-600">{{ $slot }}</div>
        @if (filled($href))
            <x-voltiva.button :href="$href" variant="secondary" class="mt-8">{{ $linkLabel }}</x-voltiva.button>
        @endif
    </div>
</section>
