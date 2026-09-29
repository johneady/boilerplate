{{--
    The brand mark, rendered in the sidebar, the auth pages and the public
    header. It is the logo uploaded from the admin panel's Brand settings
    when one is stored, and the bundled mark below otherwise.

    $logoMarkUrl is composed onto every view (AppServiceProvider), so no call
    site has to pass it -- a mark rendered inside a component that does not
    receive the variable still resolves it, and null simply falls through to
    the default.

    Call sites size the mark with classes such as size-7. Those apply to both
    branches, so the uploaded image carries object-contain and the same square
    box: the conversion is a square cover crop, which fits those slots without
    distortion.

    The mark is decorative: EVERY call site already renders the business name
    as text beside it (the public header and the auth lockups literally, the
    sidebar through flux:brand's :name). An alt of the business name would
    therefore make a screen reader announce the link as "Acme Acme", so the
    image is given an empty alt and the svg is hidden instead.
--}}
@if (($logoMarkUrl ?? null) !== null)
    <img src="{{ $logoMarkUrl }}" alt="" {{ $attributes->class('aspect-square object-contain') }} />
@else
    {{--
        The Sillage mark: a flacon in white on a plum tile, with the gold
        trail of scent (the sillage itself) curling out of it. Colours come
        from the mark's own <defs>, and the gradient id is uniqued per render
        because the mark appears more than once on some pages.
    --}}
    @php
        $gradientId = 'app-logo-'.Str::random(8);
    @endphp

    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="none" aria-hidden="true" {{ $attributes }}>
        <defs>
            <linearGradient id="{{ $gradientId }}" x1="2" y1="2" x2="46" y2="46" gradientUnits="userSpaceOnUse">
                <stop stop-color="#A8558E" />
                <stop offset="1" stop-color="#4F2743" />
            </linearGradient>
        </defs>
        <path
            fill="url(#{{ $gradientId }})"
            d="M15 2h18c7.18 0 13 5.82 13 13v18c0 7.18-5.82 13-13 13H15C7.82 46 2 40.18 2 33V15C2 7.82 7.82 2 15 2Z"
        />
        <rect x="19" y="8" width="10" height="6" rx="1.5" fill="#FCD34D" />
        <rect x="21.5" y="14" width="5" height="4" fill="#fff" />
        <rect x="12" y="18" width="24" height="22" rx="6" fill="#fff" />
        <path d="M18 33c3-6 9-2 11-7" stroke="#A8558E" stroke-width="2.4" stroke-linecap="round" />
    </svg>
@endif
