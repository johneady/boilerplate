{{--
    The XML declaration must be the very first byte of the response, so it is
    echoed rather than written literally: Blade would otherwise hand `<?xml`
    to PHP's short-open-tag parsing.
--}}
<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ($urls as $url)
        <url>
            <loc>{{ $url['loc'] }}</loc>
            <changefreq>{{ $url['changefreq'] }}</changefreq>
            <priority>{{ $url['priority'] }}</priority>
        </url>
    @endforeach
</urlset>
