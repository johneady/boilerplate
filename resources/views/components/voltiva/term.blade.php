@props([
    'key',
    'label' => null,
])

{{--
    A technical term with its plain-language explanation one click away.

    The brief asks for clickable explanations of L6e, L7e, Ah, kWh, CoC, VIN,
    LiFePO4, EPS and EU type approval; the explanations live in
    App\Voltiva\Glossary so a term reads the same on every page. A key with no
    entry renders as plain text rather than failing.

    Anchored with Alpine's x-anchor, which flips and shifts the pop-up to stay
    on screen -- a term near the right edge of a phone would otherwise open
    half off it.
--}}
@php($entry = \App\Voltiva\Glossary::find($key))

@if ($entry === null)
    {{ $label ?? $key }}
@else
    <span x-data="{ open: false }" class="inline" @keydown.escape="open = false" @click.outside="open = false">
        <button
            type="button"
            x-ref="trigger"
            @click="open = ! open"
            :aria-expanded="open"
            class="hover:decoration-volt-600 cursor-help underline decoration-neutral-400 decoration-dotted underline-offset-4 transition"
        >
            {{ $label ?? $entry['term'] }}
        </button>
        <span
            x-cloak
            x-show="open"
            x-transition.opacity.duration.150ms
            x-anchor.bottom-start.offset.8="$refs.trigger"
            role="tooltip"
            class="z-50 block w-72 max-w-[85vw] bg-neutral-950 p-4 text-left text-sm leading-relaxed font-normal tracking-normal text-white normal-case shadow-xl"
        >
            <span class="block font-medium">{{ __($entry['title']) }}</span>
            <span class="mt-1.5 block text-neutral-300">{{ __($entry['explanation']) }}</span>
        </span>
    </span>
@endif
