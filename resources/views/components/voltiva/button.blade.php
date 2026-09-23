@props([
    'href' => null,
    'variant' => 'primary',
    'type' => 'button',
])

{{--
    The site's buttons: square, quiet, black or outlined -- the brief's "clean
    buttons". `light` is for use on photographs and dark bands.
--}}
@php
    $classes = match ($variant) {
        'secondary' => 'border border-neutral-950 text-neutral-950 hover:bg-neutral-950 hover:text-white',
        'light' => 'bg-white text-neutral-950 hover:bg-neutral-200',
        'outline-light' => 'border border-white text-white hover:bg-white hover:text-neutral-950',
        default => 'bg-neutral-950 text-white hover:bg-neutral-800',
    };

    $classes = 'inline-flex items-center justify-center gap-2 px-6 py-3 text-sm font-medium tracking-wide transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-volt-600 disabled:opacity-50 '.$classes;
@endphp

@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
