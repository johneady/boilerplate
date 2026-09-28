<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>

{{--
    The blog's Atom feed.

    Like the sitemap, the XML declaration is echoed through PHP because Blade
    would hand a literal <?xml to PHP's short-open-tag parsing.

    Every URL is absolute: a feed is read by external subscribers with no
    origin of ours to resolve a relative path against.
--}}
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>{{ __(':business — Blog', ['business' => $blogName]) }}</title>
    <subtitle>{{ __('News, notes and the occasional thing we learned the hard way.') }}</subtitle>
    <id>{{ $feedId }}</id>
    <link rel="alternate" href="{{ $feedUrl }}" />
    <link rel="self" href="{{ $selfUrl }}" />
    <updated>{{ $updated->toRfc3339String() }}</updated>

    @foreach ($posts as $post)
        <entry>
            <title>{{ $post->title }}</title>
            <link rel="alternate" href="{{ route('blog.show', $post) }}" />
            <id>{{ route('blog.show', $post) }}</id>
            <published>{{ $post->published_at->toRfc3339String() }}</published>
            <updated>{{ $post->feedUpdatedAt()->toRfc3339String() }}</updated>
            <author>
                <name>{{ $post->author?->name ?? $blogName }}</name>
            </author>
            <summary type="text">{{ $post->excerpt() }}</summary>
        </entry>
    @endforeach
</feed>
