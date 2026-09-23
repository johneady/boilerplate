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

        The wheat stalk is drawn in white rather than knocked out of the tile:
        a knockout shows whatever sits behind the mark, which turns it black on
        the dark auth backdrop. The warm gradient is saturated enough that a
        white stalk holds contrast against every stop, in both themes.
    --}}
    @php
        $gradientId = 'app-logo-'.Str::random(8);
    @endphp

    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="none" aria-hidden="true" {{ $attributes }}>
        <defs>
            <linearGradient id="{{ $gradientId }}" x1="2" y1="2" x2="46" y2="46" gradientUnits="userSpaceOnUse">
                <stop stop-color="#F6C96B" />
                <stop offset="0.5" stop-color="#EA8A3A" />
                <stop offset="1" stop-color="#C2452D" />
            </linearGradient>
        </defs>
        <path
            fill="url(#{{ $gradientId }})"
            d="M15 2h18c7.18 0 13 5.82 13 13v18c0 7.18-5.82 13-13 13H15C7.82 46 2 40.18 2 33V15C2 7.82 7.82 2 15 2Z"
        />
        <g fill="#fff">
            <path d="M23 40.5V14h2v26.5z" />
            <ellipse cx="24" cy="10.8" rx="2.6" ry="4.4" />
            <ellipse cx="19.6" cy="17.4" rx="2.5" ry="4.6" transform="rotate(-38 19.6 17.4)" />
            <ellipse cx="28.4" cy="17.4" rx="2.5" ry="4.6" transform="rotate(38 28.4 17.4)" />
            <ellipse cx="19.6" cy="24.6" rx="2.5" ry="4.6" transform="rotate(-38 19.6 24.6)" />
            <ellipse cx="28.4" cy="24.6" rx="2.5" ry="4.6" transform="rotate(38 28.4 24.6)" />
            <ellipse cx="19.6" cy="31.8" rx="2.5" ry="4.6" transform="rotate(-38 19.6 31.8)" />
            <ellipse cx="28.4" cy="31.8" rx="2.5" ry="4.6" transform="rotate(38 28.4 31.8)" />
        </g>
    </svg>
@endif
