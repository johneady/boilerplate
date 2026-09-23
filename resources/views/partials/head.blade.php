<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@php
    // The site-wide title: the SEO setting when one is stored, the business
    // name otherwise. Pages with their own title keep the "Page - Business"
    // suffix pattern instead.
    $metaTitle = filled($seoTitle ?? null) ? $seoTitle : $businessName;
    $pageTitle = filled($title ?? null) ? $title.' - '.$businessName : $metaTitle;
@endphp

<title>{{ $pageTitle }}</title>

@if (! ($allowSearchIndexing ?? true))
    <meta name="robots" content="noindex, nofollow" />
@endif

@if (filled($seoDescription ?? null))
    <meta name="description" content="{{ $seoDescription }}" />
@endif

<link rel="canonical" href="{{ url()->current() }}" />

{{--
    The favicon.ico link always renders: it is the one format every browser
    understands, so it stays as the fallback when a custom icon is stored in
    a format an older browser cannot decode.
--}}
<link rel="icon" href="/favicon.ico" sizes="any" />
@if (($faviconUrl ?? null) !== null)
    <link rel="icon" href="{{ $faviconUrl }}" type="{{ $logoMime }}" sizes="any" />
@else
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
@endif

@if (($appleTouchIconUrl ?? null) !== null)
    <link rel="apple-touch-icon" href="{{ $appleTouchIconUrl }}" />
@else
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
@endif

<meta property="og:type" content="website" />
<meta property="og:site_name" content="{{ $businessName }}" />
<meta property="og:title" content="{{ $pageTitle }}" />
@if (filled($seoDescription ?? null))
    <meta property="og:description" content="{{ $seoDescription }}" />
@endif
<meta property="og:url" content="{{ url()->current() }}" />
{{--
    A page's own photograph (a car, an article) wins over the site-wide
    social image, and earns the large card since it is a real photo. Named
    differently from $socialImageUrl because the composer sets that one after
    the page's data is bound and would overwrite it.
--}}
@if (($pageImageUrl ?? null) !== null)
    <meta property="og:image" content="{{ url($pageImageUrl) }}" />
    <meta name="twitter:card" content="summary_large_image" />
@elseif (($socialImageUrl ?? null) !== null)
    <meta property="og:image" content="{{ url($socialImageUrl) }}" />
    <meta name="twitter:card" content="summary" />
@endif

{{--
    Structured data describing the organisation behind the site. Omitted along
    with indexing, because a noindex page has nothing to gain from it.

    JSON_UNESCAPED_SLASHES keeps URLs readable; HEX_TAG and HEX_AMP escape the
    characters that could otherwise close this script element early from
    inside a stored business name.
--}}
@if (($allowSearchIndexing ?? true) && filled($organizationSchema ?? null))
    <script type="application/ld+json">
        {!! json_encode($organizationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}
    </script>
@endif

{{-- The page's own structured data (a Car, an Article), encoded the same way. --}}
@if (($allowSearchIndexing ?? true) && filled($pageSchema ?? null))
    <script type="application/ld+json">
        {!! json_encode($pageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}
    </script>
@endif

{{--
    Gated with the rest of the indexing signals: with indexing off the sitemap
    is served empty, so advertising it would point a crawler at nothing while
    the page beside it says noindex. The signals have to agree.
--}}
@if ($allowSearchIndexing ?? true)
    <link rel="sitemap" type="application/xml" href="{{ route('sitemap') }}" />
@endif

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
{{--
    The public Voltiva site is white by design, so it skips Flux's appearance
    script -- which would otherwise add html.dark for visitors whose system
    is in dark mode.
--}}
@unless ($lightOnly ?? false)
    @fluxAppearance
@endunless
