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
        The gradient is painted from the mark's own <defs>, so it keeps its
        colours rather than inheriting the call site's text colour the way the
        previous monochrome mark did.

        The gradient id is uniqued per render: the mark appears more than once
        on a page (the sidebar brand and the mobile header, for one), and a
        duplicate id makes every later instance resolve the first one's stops.

        The coffee cup is drawn in white rather than knocked out of the tile: a
        knockout shows whatever sits behind the mark, which turns it black on
        the dark auth backdrop. The green gradient is dark enough that a white
        cup holds contrast against every stop, in both themes.
    --}}
    @php
        $gradientId = 'app-logo-'.Str::random(8);
    @endphp

    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="none" aria-hidden="true" {{ $attributes }}>
        <defs>
            <linearGradient id="{{ $gradientId }}" x1="2" y1="2" x2="46" y2="46" gradientUnits="userSpaceOnUse">
                <stop stop-color="#34D399" />
                <stop offset="0.5" stop-color="#047857" />
                <stop offset="1" stop-color="#064E3B" />
            </linearGradient>
        </defs>
        <path
            fill="url(#{{ $gradientId }})"
            d="M15 2h18c7.18 0 13 5.82 13 13v18c0 7.18-5.82 13-13 13H15C7.82 46 2 40.18 2 33V15C2 7.82 7.82 2 15 2Z"
        />
        <g fill="#fff">
            <path d="M12 20h19v8.5a9.5 9.5 0 0 1-9.5 9.5 9.5 9.5 0 0 1-9.5-9.5Z" />
            <rect x="9" y="39" width="25" height="2.6" rx="1.3" />
        </g>
        <g stroke="#fff" stroke-linecap="round" fill="none">
            <path d="M31 23h2.5a3.75 3.75 0 0 1 0 7.5H30.5" stroke-width="2.6" />
            <path d="M17 8.5c-1.6 2 1.6 4 0 6.5M21.5 7c-1.6 2 1.6 4 0 6.5M26 8.5c-1.6 2 1.6 4 0 6.5" stroke-width="2" />
        </g>
    </svg>
@endif
