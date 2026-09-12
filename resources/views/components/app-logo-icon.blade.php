{{--
    The brand mark, rendered in the sidebar, the auth pages and the public
    header. It is the logo uploaded from the admin panel's SEO & brand settings
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

        The bolt is drawn in white rather than knocked out of the tile: a
        knockout shows whatever sits behind the mark, which turns the bolt
        black on the dark auth backdrop. The gradient is saturated enough that
        a white bolt holds contrast against every stop, in both themes.
    --}}
    @php
        $gradientId = 'app-logo-'.Str::random(8);
    @endphp

    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="none" aria-hidden="true" {{ $attributes }}>
        <defs>
            <linearGradient id="{{ $gradientId }}" x1="2" y1="2" x2="46" y2="46" gradientUnits="userSpaceOnUse">
                <stop stop-color="#22D3EE" />
                <stop offset="0.5" stop-color="#6366F1" />
                <stop offset="1" stop-color="#E879F9" />
            </linearGradient>
        </defs>
        <path
            fill="url(#{{ $gradientId }})"
            d="M15 2h18c7.18 0 13 5.82 13 13v18c0 7.18-5.82 13-13 13H15C7.82 46 2 40.18 2 33V15C2 7.82 7.82 2 15 2Z"
        />
        <path fill="#fff" d="M26.5 7.5 12.5 28h8.2l-1.3 13.2L35.5 21h-8.4l1.4-13.5Z" />
    </svg>
@endif
