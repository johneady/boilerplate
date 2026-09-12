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
@if (($socialImageUrl ?? null) !== null)
    <meta property="og:image" content="{{ url($socialImageUrl) }}" />
    <meta name="twitter:card" content="summary" />
@endif

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
