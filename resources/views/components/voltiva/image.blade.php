@props([
    'src' => null,
    'alt' => '',
    'ratio' => 'aspect-4/3',
    'eager' => false,
])

{{--
    A fixed image area. The box sets the shape and the photo is cropped to
    fill it (object-cover), so replacing a photograph in the admin panel --
    whatever its size or orientation -- never changes the layout, as the
    brief requires. Lazy-loaded unless it is above the fold.
--}}
<div {{ $attributes->class([$ratio, 'relative overflow-hidden bg-neutral-100']) }}>
    @if (filled($src))
        <img
            src="{{ $src }}"
            alt="{{ $alt }}"
            @if ($eager) fetchpriority="high" @else loading="lazy" @endif
            decoding="async"
            class="absolute inset-0 size-full object-cover"
        />
    @endif
    {{ $slot }}
</div>
