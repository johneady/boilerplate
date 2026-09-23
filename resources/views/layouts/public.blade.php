@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'schema' => null,
])

{{--
    The shell every public page renders inside: the home page, the car range,
    News & Advice, the content pages and the forms.

    Voltiva's site is deliberately white-only (the brief asks for a white
    background and black text), so unlike the boilerplate's public shell this
    one does not follow the visitor's colour scheme: the head skips Flux's
    appearance script and the body is scoped with .voltiva, which also turns
    Flux's accent black so its buttons match the site's own.

    Full-width: each page sets its own container, because the photography is
    meant to run edge to edge.
--}}
@php
    $title = filled($title) ? $title : null;

    // Passed INTO the @include rather than set here: the partials.head
    // composer runs after this block. See .ai/rules/components-providers.md.
    $headData = array_filter([
        'seoDescription' => filled($description) ? $description : null,
        'pageImageUrl' => filled($image) ? $image : null,
        'pageSchema' => filled($schema) ? $schema : null,
        'lightOnly' => true,
    ]);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @include('partials.head', $headData)
</head>
<body class="voltiva min-h-dvh bg-white text-neutral-950 antialiased">
    <a
        href="#main"
        class="sr-only z-50 bg-neutral-950 px-4 py-2 text-sm text-white focus:not-sr-only focus:fixed focus:top-2 focus:left-2"
    >
        {{ __('Skip to content') }}
    </a>

    <x-voltiva.header />

    <div id="main" class="flex min-h-[60vh] flex-col">{{ $slot }}</div>

    <x-voltiva.footer />

    <flux:toast position="bottom end" />
    {{--
        Explicit, because Livewire only auto-injects its script (and the
        Alpine it bundles) on pages that render a component -- and the
        header's menus and the glossary pop-ups need Alpine on every page.
    --}}
    @livewireScripts
    @fluxScripts
</body>
</html>
