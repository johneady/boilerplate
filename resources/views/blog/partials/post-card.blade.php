{{--
    One blog post card, shared by the listing, the archives and a post page's
    "more posts" row.

    The cover renders only when one exists (a cover mid-processing or a post
    without one returns null), so the card never links a broken image.
--}}
<article class="group flex flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white/60 dark:border-neutral-800 dark:bg-neutral-900/40">
    @if ($coverUrl = $post->coverUrl('card'))
        <a href="{{ route('blog.show', $post) }}" class="block aspect-video overflow-hidden" wire:navigate>
            <img
                src="{{ $coverUrl }}"
                alt="{{ $post->title }}"
                loading="lazy"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
            />
        </a>
    @endif

    <div class="flex flex-1 flex-col p-6">
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

            <span class="text-neutral-500 dark:text-neutral-400">
                {{ app(\App\Settings\Settings::class)->formatDate($post->published_at) }}
            </span>

            <span class="text-neutral-400 dark:text-neutral-500">&middot;</span>

            <span class="text-neutral-500 dark:text-neutral-400">
                {{ __(':minutes min read', ['minutes' => $post->readingTime()]) }}
            </span>
        </div>

        <h3 class="mt-3 text-lg font-semibold tracking-tight">
            <a href="{{ route('blog.show', $post) }}" class="hover:underline" wire:navigate> {{ $post->title }} </a>
        </h3>

        @if (($excerpt = $post->excerpt()) !== '')
            <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">
                {{ $excerpt }}
            </p>
        @endif

        <p class="mt-4 text-sm text-neutral-500 dark:text-neutral-400">
            {{ __('By :author', ['author' => $post->author?->name ?? $businessName]) }}
        </p>
    </div>
</article>
