{{--
    The blog's front page: newest published posts, category links, pagination.
--}}
<x-layouts::public :title="__('Blog')">
    <main class="flex-1 py-12">
        <header class="max-w-3xl">
            <h1 class="text-4xl font-semibold tracking-tight text-balance">{{ __('Blog') }}</h1>

            <p class="mt-4 text-lg leading-relaxed text-neutral-600 dark:text-neutral-400">
                {{ __('News, notes and the occasional thing we learned the hard way.') }}
            </p>
        </header>

        @if ($categories->isNotEmpty())
            <nav aria-label="{{ __('Browse by category') }}" class="mt-8 flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    <a
                        href="{{ route('blog.category', $category) }}"
                        class="rounded-full border border-neutral-200 px-3 py-1 text-sm text-neutral-600 transition hover:border-sky-300 hover:text-sky-700 dark:border-neutral-800 dark:text-neutral-400 dark:hover:border-sky-500/40 dark:hover:text-sky-400"
                        wire:navigate
                    >
                        {{ $category->name }}
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="mt-10 grid gap-8 sm:grid-cols-2">
            @foreach ($posts as $post)
                @include('blog.partials.post-card', ['post' => $post])
            @endforeach
        </div>

        @if ($posts->isEmpty())
            <p class="mt-10 rounded-xl border border-dashed border-neutral-300 p-8 text-center text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                {{ __('There are no posts yet. Check back soon.') }}
            </p>
        @endif

        <div class="mt-10">{{ $posts->links() }}</div>

        <p class="mt-6 text-sm text-neutral-500 dark:text-neutral-400">
            <a href="{{ route('blog.feed') }}" class="hover:underline">{{ __('Subscribe to the feed') }}</a>
        </p>
    </main>
</x-layouts::public>
