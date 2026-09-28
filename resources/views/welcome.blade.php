{{--
    The marketing home page. The HTML shell, header nav and footer that used to
    live here are now layouts/public.blade.php, shared with the content pages and
    the contact form.

    No :title is passed on purpose: the home page takes the site-wide SEO title
    rather than prefixing it with a page name.
--}}
<x-layouts::public>
    <main class="flex flex-1 flex-col justify-center py-16">
        <div class="max-w-3xl">
            <flux:badge size="sm" color="sky" inset="top bottom">{{ __('Est. whenever') }}</flux:badge>

            <h1 class="mt-6 text-5xl font-semibold tracking-tight text-balance sm:text-6xl">
                {{ __('We make the thing that holds the other things.') }}
            </h1>

            <p class="mt-6 max-w-2xl text-lg leading-relaxed text-neutral-600 dark:text-neutral-400">
                {{
                    __('Since the beginning, :business has specialised in the load-bearing middle
                    — the quiet layer nobody photographs and everybody depends on. Our output is measured in
                    afternoons not spent rewriting the same login page.', ['business' => $businessName])
                }}
            </p>

            <div class="mt-10 flex flex-wrap items-center gap-3">
                <flux:button :href="route('login')" variant="primary" wire:navigate>
                    {{ __('Get started') }}
                </flux:button>
                <flux:button href="#principles" variant="ghost"> {{ __('Read the brochure') }} </flux:button>
            </div>
        </div>

        <div id="principles" class="mt-24 grid gap-8 sm:grid-cols-3">
            @foreach ([
                ['heading' => __('Structurally sound'), 'body' => __('Every beam accounted for, including the ones holding up the beams. Independently verified by people who enjoy that sort of thing.')],
                ['heading' => __('Quietly durable'), 'body' => __('Built to be ignored for years at a time. The highest compliment our work receives is no compliment at all.')],
                ['heading' => __('Sensibly finished'), 'body' => __('Sanded where it matters, left honest where it does not. We stop before the point of diminishing returns, on principle.')],
            ] as $principle)
                <div class="rounded-xl border border-neutral-200 bg-white/60 p-6 dark:border-neutral-800 dark:bg-neutral-900/40">
                    <flux:heading size="lg">{{ $principle['heading'] }}</flux:heading>
                    <p class="mt-2 text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">
                        {{ $principle['body'] }}
                    </p>
                </div>
            @endforeach
        </div>

        {{--
            The latest posts, rendered only while the blog is switched on and
            holding at least one published post. $latestPosts arrives as a
            closure (see AppServiceProvider) so a blog that is off costs the
            home page no query at all.
        --}}
        {{-- Resolved once here rather than invoked in the condition and again
             in the loop: the closure runs the query each time it is called. --}}
        @php($latestPosts = $latestPosts())

        @if ($latestPosts !== null && $latestPosts->isNotEmpty())
            <section class="mt-24">
                <div class="flex items-end justify-between gap-4">
                    <h2 class="text-2xl font-semibold tracking-tight">{{ __('From the blog') }}</h2>

                    <a
                        href="{{ route('blog.index') }}"
                        class="text-sm font-medium text-sky-700 hover:underline dark:text-sky-400"
                        wire:navigate
                    >
                        {{ __('View all posts') }}
                    </a>
                </div>

                <div class="mt-8 grid gap-8 sm:grid-cols-3">
                    @foreach ($latestPosts as $post)
                        <div class="rounded-xl border border-neutral-200 bg-white/60 p-6 dark:border-neutral-800 dark:bg-neutral-900/40">
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ app(\App\Settings\Settings::class)->formatDate($post->published_at) }}
                            </p>

                            <h3 class="mt-2 font-semibold tracking-tight">
                                <a href="{{ route('blog.show', $post) }}" class="hover:underline" wire:navigate>
                                    {{ $post->title }}
                                </a>
                            </h3>

                            <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">
                                {{ $post->excerpt() }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </main>
</x-layouts::public>
