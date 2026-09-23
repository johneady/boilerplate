@props([
    'article',
])

{{-- An article in a grid, on the home page, News & Advice and topic pages. --}}
<article {{ $attributes->class('group relative flex flex-col') }}>
    <x-voltiva.image :src="$article->imageUrl()" :alt="$article->title" ratio="aspect-3/2" />
    <p class="text-volt-700 mt-5 text-xs font-medium tracking-[0.2em] uppercase">{{ __($article->topic->label()) }}</p>
    <h3 class="mt-2 text-lg font-medium tracking-tight text-balance">
        <a
            href="{{ route('news.show', $article) }}"
            class="group-hover:underline after:absolute after:inset-0"
        >{{ $article->title }}</a>
    </h3>
    <p class="mt-2 text-sm leading-relaxed text-neutral-600">{{ $article->excerpt }}</p>
    <p class="mt-3 text-xs text-neutral-500">
        {{ __(':minutes min read', ['minutes' => $article->readingMinutes()]) }}
    </p>
</article>
