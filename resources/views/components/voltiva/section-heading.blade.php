@props([
    'eyebrow' => null,
    'title',
    'intro' => null,
    'as' => 'h2',
])

{{-- An eyebrow, a heading and an optional intro, left-aligned as the brief asks. --}}
<div {{ $attributes->class('max-w-3xl') }}>
    @if (filled($eyebrow))
        <p class="text-volt-700 text-xs font-medium tracking-[0.2em] uppercase">{{ $eyebrow }}</p>
    @endif
    <{{ $as }}
        @class(['text-3xl font-medium tracking-tight text-balance sm:text-4xl', 'mt-3' => filled($eyebrow)])
        >{{ $title }}</{{ $as }}
    >
    @if (filled($intro))
        <p class="mt-4 text-lg leading-relaxed text-neutral-600">{{ $intro }}</p>
    @endif
</div>
