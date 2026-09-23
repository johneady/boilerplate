{{--
    The About page: the administrator's Markdown body, framed with the kitchen
    photograph and a nudge towards the menu and the order form.

    Chosen by slug in PageController::TEMPLATES. The body goes through
    Page::renderedBody() exactly as in pages/show.blade.php -- see that
    template and .ai/rules/resources-pages.md for why the {!! !!} is safe.
--}}
<x-layouts::public :title="$page->title" :description="$page->seo_description">
    <main class="flex-1 py-8 sm:py-12">
        <section class="grid items-center gap-10 lg:grid-cols-2">
            <div>
                <p class="text-sm font-semibold tracking-wide text-amber-700 uppercase dark:text-amber-400">
                    {{ $page->title }}
                </p>
                <h1 class="font-display mt-3 text-5xl leading-[1.05] font-semibold tracking-tight text-balance">
                    {{ __('Real butter, slow dough and a lot of love.') }}
                </h1>
                <p class="mt-6 max-w-lg text-lg leading-relaxed text-stone-600 dark:text-stone-400">
                    {{ __(':business is a one-oven home bakery. Everything is made by hand, to order, in small batches — so it is fresh on the day you need it.', ['business' => $businessName]) }}
                </p>
            </div>

            <figure class="relative">
                <img
                    src="{{ asset('images/bakery/about-kneading.webp') }}"
                    alt="{{ __('Hands kneading bread dough on a floured table') }}"
                    class="aspect-[4/3] w-full rounded-3xl object-cover shadow-xl shadow-amber-900/15"
                />
                <figcaption class="mt-2 text-right text-xs text-stone-500">
                    {{ __('Photo: Shixart1985, CC BY 2.0, via Wikimedia Commons') }}
                </figcaption>
            </figure>
        </section>

        @unless ($page->is_published)
            <div class="mt-8 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                {{ __('This page is a draft. Only people who can edit it see this.') }}
            </div>
        @endunless

        <div class="mt-16 grid gap-12 lg:grid-cols-[1fr_20rem]">
            <article class="[&_a]:font-medium [&_a]:text-amber-800 [&_a]:underline [&_a]:underline-offset-2 dark:[&_a]:text-amber-300 [&_h2]:mt-10 [&_h2]:mb-3 [&_h2]:font-display [&_h2]:text-3xl [&_h2]:font-semibold [&_h2]:text-stone-900 first:[&_h2]:mt-0 dark:[&_h2]:text-stone-100 [&_h3]:mt-8 [&_h3]:mb-2 [&_h3]:text-xl [&_h3]:font-semibold [&_li]:my-1.5 [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-4 [&_strong]:font-semibold [&_strong]:text-stone-900 dark:[&_strong]:text-stone-100 [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:marker:text-amber-600 max-w-2xl text-lg leading-relaxed text-stone-700 dark:text-stone-300">
                {!! $page->renderedBody() !!}
            </article>

            <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                @foreach ([
                    ['icon' => 'clock', 'title' => __('48-hour sourdough'), 'body' => __('Wild yeast and patience, never shortcuts.')],
                    ['icon' => 'sparkles', 'title' => __('Made to order'), 'body' => __('We bake for your date, so nothing sits on a shelf.')],
                    ['icon' => 'heart', 'title' => __('Allergy-aware'), 'body' => __('Vegan, dairy-free and gluten-free options on the menu.')],
                ] as $value)
                    <div class="flex gap-4 rounded-2xl border border-amber-200/70 bg-white/80 p-5 dark:border-white/10 dark:bg-stone-900/60">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                            <flux:icon :icon="$value['icon']" variant="mini" />
                        </div>
                        <div>
                            <p class="font-semibold">{{ $value['title'] }}</p>
                            <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">{{ $value['body'] }}</p>
                        </div>
                    </div>
                @endforeach

                <a
                    href="{{ route('order') }}"
                    class="flex items-center justify-center gap-2 rounded-full bg-amber-700 px-5 py-3 font-semibold text-white hover:bg-amber-800 dark:bg-amber-500 dark:text-stone-950"
                    wire:navigate
                >
                    {{ __('Request an order') }}
                    <flux:icon.arrow-right variant="micro" />
                </a>
                <a
                    href="{{ route('home') }}#menu"
                    class="block text-center text-sm font-medium text-stone-600 underline-offset-2 hover:underline dark:text-stone-400"
                    wire:navigate
                >
                    {{ __('or browse the menu') }}
                </a>
            </aside>
        </div>
    </main>
</x-layouts::public>
