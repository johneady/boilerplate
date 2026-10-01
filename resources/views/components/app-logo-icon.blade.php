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
        Wanderlight's mark: a sun rising over the sea, on an ocean-teal tile.

        The gradient id is uniqued per render: the mark appears more than once
        on a page (the sidebar brand and the mobile header, for one), and a
        duplicate id makes every later instance resolve the first one's stops.

        The sun and waves are drawn in their own colours rather than knocked
        out of the tile, so they hold contrast on the dark auth backdrop too.
    --}}
    @php
        $gradientId = 'app-logo-'.Str::random(8);
    @endphp

    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="none" aria-hidden="true" {{ $attributes }}>
        <defs>
            <linearGradient id="{{ $gradientId }}" x1="2" y1="2" x2="46" y2="46" gradientUnits="userSpaceOnUse">
                <stop stop-color="#14B8A6" />
                <stop offset="1" stop-color="#0E5E6F" />
            </linearGradient>
        </defs>
        <path
            fill="url(#{{ $gradientId }})"
            d="M15 2h18c7.18 0 13 5.82 13 13v18c0 7.18-5.82 13-13 13H15C7.82 46 2 40.18 2 33V15C2 7.82 7.82 2 15 2Z"
        />
        <path fill="#FDBA74" d="M13 27a11 11 0 0 1 22 0Z" />
        <path
            stroke="#fff"
            stroke-width="2.8"
            stroke-linecap="round"
            d="M9 31.5c2.5 0 2.5-1.8 5-1.8s2.5 1.8 5 1.8 2.5-1.8 5-1.8 2.5 1.8 5 1.8 2.5-1.8 5-1.8 2.5 1.8 5 1.8"
        />
        <path
            stroke="#fff"
            stroke-opacity=".7"
            stroke-width="2.8"
            stroke-linecap="round"
            d="M14 37.5c2.5 0 2.5-1.8 5-1.8s2.5 1.8 5 1.8 2.5-1.8 5-1.8 2.5 1.8 5 1.8"
        />
    </svg>
@endif
