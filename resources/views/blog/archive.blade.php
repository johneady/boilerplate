{{--
    A category or tag archive: the same card grid as the blog's front page,
    headed by whichever grouping the visitor followed a link into.
--}}
<x-layouts::public :title="$heading" :description="$description">
    <main class="flex-1 py-12">
        <header class="max-w-3xl">
            <p class="text-sm font-medium tracking-wide text-sky-700 uppercase dark:text-sky-400">
                <a href="{{ route('blog.index') }}" class="hover:underline" wire:navigate>{{ __('Blog') }}</a>
            </p>

            <h1 class="mt-2 text-4xl font-semibold tracking-tight text-balance">{{ $heading }}</h1>

            <p class="mt-4 text-lg leading-relaxed text-neutral-600 dark:text-neutral-400">{{ $description }}</p>
        </header>

        <div class="mt-10 grid gap-8 sm:grid-cols-2">
            @foreach ($posts as $post)
                @include('blog.partials.post-card', ['post' => $post])
            @endforeach
        </div>

        @if ($posts->isEmpty())
            <p class="mt-10 rounded-xl border border-dashed border-neutral-300 p-8 text-center text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                {{ __('There are no posts here yet.') }}
            </p>
        @endif

        <div class="mt-10">{{ $posts->links() }}</div>
    </main>
</x-layouts::public>
