{{--
    A single blog post.

    The body is Markdown rendered by Post::renderedBody(), which escapes any
    HTML in the source -- see Page::renderedBody() for why the {!! !!} below
    is safe and what must not be removed from it.

    Element styling uses the same descendant-selector wrapper as the content
    pages, for the same reason: Markdown output carries no classes of its own.
--}}
<x-layouts::public :title="$post->title" :description="$post->excerpt()">
    <main class="flex-1 py-12">
        <article class="mx-auto max-w-3xl">
            <header>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    @if ($post->category !== null)
                        <a
                            href="{{ route('blog.category', $post->category) }}"
                            class="rounded-full bg-sky-50 px-2.5 py-0.5 font-medium text-sky-700 dark:bg-sky-500/10 dark:text-sky-400"
                            wire:navigate
                        >
                            {{ $post->category->name }}
                        </a>
                    @endif

                    @if ($post->isPublished())
                        <span class="text-neutral-500 dark:text-neutral-400">
                            {{ app(\App\Settings\Settings::class)->formatDate($post->published_at) }}
                        </span>

                        <span class="text-neutral-400 dark:text-neutral-500">&middot;</span>

                        <span class="text-neutral-500 dark:text-neutral-400">
                            {{ __(':minutes min read', ['minutes' => $post->readingTime()]) }}
                        </span>
                    @endif
                </div>

                <h1 class="mt-4 text-4xl font-semibold tracking-tight text-balance">{{ $post->title }}</h1>

                <p class="mt-4 flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                    {{ __('By :author', ['author' => $post->author?->name ?? $businessName]) }}
                </p>
            </header>

            @unless ($post->isPublished())
                <div class="mt-8 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                    {{
                        $post->isScheduled()
                        ? __('This post is scheduled. Only people who can edit it see this until :date.', [
                            'date' => app(\App\Settings\Settings::class)->formatDateTime($post->published_at),
                        ])
                        : __('This post is a draft. Only people who can edit it see this.')
                    }}
                </div>
            @endunless

            @if ($coverUrl = $post->coverUrl('wide'))
                <img
                    src="{{ $coverUrl }}"
                    alt="{{ $post->title }}"
                    class="mt-8 aspect-video w-full rounded-xl object-cover"
                />
            @endif

            <div class="[&_a]:font-medium [&_a]:text-sky-700 [&_a]:underline [&_a]:underline-offset-2 dark:[&_a]:text-sky-400 [&_blockquote]:my-6 [&_blockquote]:border-l-2 [&_blockquote]:border-neutral-300 [&_blockquote]:pl-4 [&_blockquote]:text-neutral-600 dark:[&_blockquote]:border-neutral-700 dark:[&_blockquote]:text-neutral-400 [&_code]:rounded [&_code]:bg-neutral-100 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:text-sm dark:[&_code]:bg-neutral-800 [&_h2]:mt-12 [&_h2]:mb-4 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_h2]:text-neutral-900 dark:[&_h2]:text-neutral-100 [&_h3]:mt-8 [&_h3]:mb-3 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-neutral-900 dark:[&_h3]:text-neutral-100 [&_h4]:mt-6 [&_h4]:mb-2 [&_h4]:text-lg [&_h4]:font-semibold [&_h4]:text-neutral-900 dark:[&_h4]:text-neutral-100 [&_hr]:my-10 [&_hr]:border-neutral-200 dark:[&_hr]:border-neutral-800 [&_li]:my-1 [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-4 [&_pre]:my-6 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-neutral-100 [&_pre]:p-4 [&_pre]:text-sm dark:[&_pre]:bg-neutral-900 [&_strong]:font-semibold [&_strong]:text-neutral-900 dark:[&_strong]:text-neutral-100 [&_table]:my-6 [&_table]:w-full [&_table]:text-left [&_table]:text-sm [&_td]:border-t [&_td]:border-neutral-200 [&_td]:py-2 [&_td]:pr-4 dark:[&_td]:border-neutral-800 [&_th]:border-b [&_th]:border-neutral-300 [&_th]:py-2 [&_th]:pr-4 [&_th]:font-semibold dark:[&_th]:border-neutral-700 [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6 mt-10 text-base leading-relaxed text-neutral-700 dark:text-neutral-300">
                {!! $post->renderedBody() !!}
            </div>

            @if ($post->tags->isNotEmpty())
                <nav aria-label="{{ __('Tags') }}" class="mt-10 flex flex-wrap gap-2">
                    @foreach ($post->tags as $tag)
                        <a
                            href="{{ route('blog.tag', $tag) }}"
                            class="rounded-full border border-neutral-200 px-3 py-1 text-sm text-neutral-600 transition hover:border-sky-300 hover:text-sky-700 dark:border-neutral-800 dark:text-neutral-400 dark:hover:border-sky-500/40 dark:hover:text-sky-400"
                            wire:navigate
                        >
                            {{ $tag->name }}
                        </a>
                    @endforeach
                </nav>
            @endif
        </article>

        @php
            $previousPost = $post->previousPost();
            $nextPost = $post->nextPost();
        @endphp

        @if ($previousPost !== null || $nextPost !== null)
            <nav
                aria-label="{{ __('More posts') }}"
                class="mx-auto mt-12 flex max-w-3xl items-stretch justify-between gap-4 border-t border-neutral-200 py-8 dark:border-neutral-800"
            >
                @if ($previousPost !== null)
                    <a href="{{ route('blog.show', $previousPost) }}" class="group flex-1 text-left" wire:navigate>
                        <span class="text-xs tracking-wide text-neutral-400 uppercase dark:text-neutral-500">
                            {{ __('Older') }}
                        </span>
                        <span class="mt-1 block font-medium group-hover:underline">{{ $previousPost->title }}</span>
                    </a>
                @else
                    <span class="flex-1"></span>
                @endif

                @if ($nextPost !== null)
                    <a href="{{ route('blog.show', $nextPost) }}" class="group flex-1 text-right" wire:navigate>
                        <span class="text-xs tracking-wide text-neutral-400 uppercase dark:text-neutral-500">
                            {{ __('Newer') }}
                        </span>
                        <span class="mt-1 block font-medium group-hover:underline">{{ $nextPost->title }}</span>
                    </a>
                @endif
            </nav>
        @endif

        @if (($relatedPosts = $post->relatedPosts(excludeIds: array_values(array_filter([$previousPost?->id, $nextPost?->id]))))->isNotEmpty())
            <section class="mx-auto mt-4 max-w-3xl">
                <h2 class="text-xl font-semibold tracking-tight">{{ __('More from the blog') }}</h2>

                <div class="mt-6 grid gap-8 sm:grid-cols-2">
                    @foreach ($relatedPosts as $relatedPost)
                        @include('blog.partials.post-card', ['post' => $relatedPost])
                    @endforeach
                </div>
            </section>
        @endif
    </main>
</x-layouts::public>
