{{--
    The home page: search first, then the community's size, the most followed
    perfumes and a way in by olfactory family.

    No :title is passed on purpose: the home page takes the site-wide SEO title
    rather than prefixing it with a page name.
--}}
<x-layouts::public>
    <main class="flex flex-1 flex-col py-12 sm:py-16">
        <section class="max-w-3xl">
            <flux:badge size="sm" color="fuchsia" inset="top bottom">{{ __('The open perfume database') }}</flux:badge>

            <h1 class="mt-6 font-display text-5xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-7xl">
                {{ __('Every fragrance, note by note.') }}
            </h1>

            <p class="mt-6 max-w-2xl text-lg leading-relaxed text-neutral-600 dark:text-neutral-400">
                {{ __(':business is a free, community-followed guide to perfumes: who made them, what is in them, and what to try next. Follow the ones you love and see what everyone else is wearing.', ['business' => $businessName]) }}
            </p>

            <form action="{{ route('perfumes.index') }}" method="get" class="mt-8 flex max-w-xl gap-2" role="search">
                <div class="flex-1">
                    <flux:input name="q" icon="magnifying-glass" :placeholder="__('Try “Shalimar”, “Guerlain” or “vanilla”')" :aria-label="__('Search perfumes')" />
                </div>
                <flux:button type="submit" variant="primary">{{ __('Search') }}</flux:button>
            </form>

            <dl class="mt-10 flex flex-wrap gap-x-10 gap-y-4">
                @foreach ([
                    [__('Perfumes'), $perfumeCount],
                    [__('Followers'), $members],
                    [__('Page views this month'), $monthViews],
                ] as [$label, $value])
                    <div>
                        <dd class="font-display text-4xl font-semibold [font-variant-numeric:lining-nums] text-plum-700 dark:text-plum-300">{{ number_format($value) }}</dd>
                        <dt class="text-sm text-neutral-600 dark:text-neutral-400">{{ $label }}</dt>
                    </div>
                @endforeach
            </dl>
        </section>

        @if ($mostFollowed->isNotEmpty())
            <section class="mt-20">
                <div class="flex items-end justify-between gap-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight">{{ __('Most followed') }}</h2>
                    <a href="{{ route('perfumes.index') }}" class="text-sm font-medium text-plum-700 hover:underline dark:text-plum-300" wire:navigate>
                        {{ __('Browse all perfumes') }}
                    </a>
                </div>

                <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($mostFollowed as $perfume)
                        <x-perfumes.card :perfume="$perfume" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($families->isNotEmpty())
            <section class="mt-20">
                <h2 class="font-display text-3xl font-semibold tracking-tight">{{ __('Explore by family') }}</h2>

                <div class="mt-6 flex flex-wrap gap-3">
                    @foreach ($families as $entry)
                        <a
                            href="{{ route('perfumes.index', ['family' => $entry['family']->value]) }}"
                            class="inline-flex items-center gap-2 rounded-full border border-plum-100 bg-white/80 px-4 py-2 text-sm font-medium transition hover:border-plum-300 dark:border-plum-900/60 dark:bg-plum-950/40 dark:hover:border-plum-700"
                            wire:navigate
                        >
                            <span class="size-2.5 rounded-full" style="background-color: {{ $entry['family']->color() }}"></span>
                            {{ __($entry['family']->label()) }}
                            <span class="text-neutral-500 dark:text-neutral-400">{{ $entry['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($recentlyAdded->isNotEmpty())
            <section class="mt-20">
                <h2 class="font-display text-3xl font-semibold tracking-tight">{{ __('Recently added') }}</h2>

                <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($recentlyAdded as $perfume)
                        <x-perfumes.card :perfume="$perfume" />
                    @endforeach
                </div>
            </section>
        @endif

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
            <section class="mt-20">
                <div class="flex items-end justify-between gap-4">
                    <h2 class="font-display text-3xl font-semibold tracking-tight">{{ __('From the blog') }}</h2>

                    <a
                        href="{{ route('blog.index') }}"
                        class="text-sm font-medium text-plum-700 hover:underline dark:text-plum-300"
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

        <section class="mt-20 flex flex-col items-start justify-between gap-6 rounded-3xl bg-linear-to-br from-plum-700 to-plum-950 p-8 text-white sm:flex-row sm:items-center sm:p-10">
            <div>
                <h2 class="font-display text-3xl font-semibold">{{ __('Follow the perfumes you love') }}</h2>
                <p class="mt-2 max-w-xl text-plum-100">
                    {{ __('A free account keeps your collection in one place and counts you in the community figures.') }}
                </p>
            </div>

            <div class="flex shrink-0 gap-2">
                @guest
                    @registrationEnabled
                        <flux:button :href="route('register')" wire:navigate>{{ __('Join free') }}</flux:button>
                    @endregistrationEnabled
                @endguest
                <flux:button :href="route('stats')" variant="ghost" class="text-white! hover:bg-white/10!" wire:navigate>{{ __('See the open stats') }}</flux:button>
            </div>
        </section>
    </main>
</x-layouts::public>
