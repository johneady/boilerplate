{{--
    The one article template. The body is Markdown escaped by
    Article::renderedBody(); the topic decides the "learn more" link, and an
    optional car adds that car's card -- so an article written in the admin
    panel always leads somewhere useful.
--}}
<x-layouts::public
    :title="$article->title"
    :description="$article->seo_description ?: $article->excerpt"
    :image="$article->imageUrl()"
    :schema="$article->structuredData($businessName)"
>
    <article>
        <header class="mx-auto w-full max-w-4xl px-4 pt-14 sm:px-6 lg:pt-20">
            <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-neutral-500">
                <a href="{{ route('news.index') }}" class="hover:text-neutral-950">{{ __('News & Advice') }}</a>
                <span class="mx-1.5">/</span>
                <a
                    href="{{ route('news.index', ['topic' => $article->topic->value]) }}"
                    class="hover:text-neutral-950"
                >{{ __($article->topic->label()) }}</a>
            </nav>

            @unless ($article->isVisible())
                <p class="mt-6 bg-amber-100 px-4 py-2 text-sm text-amber-900">
                    {{ __('This article is not published yet. Only people who can edit it see this page.') }}
                </p>
            @endunless

            <h1 class="mt-6 text-4xl font-medium tracking-tight text-balance sm:text-5xl">{{ $article->title }}</h1>
            <p class="mt-5 text-xl leading-relaxed text-neutral-600">{{ $article->excerpt }}</p>
            <p class="mt-6 text-sm text-neutral-500">
                @if ($article->published_at)
                    <time datetime="{{ $article->published_at->toDateString() }}">{{ $article->published_at->locale(app()->getLocale())->isoFormat('LL') }}</time>
                    ·
                @endif
                {{ __(':minutes min read', ['minutes' => $article->readingMinutes()]) }}
            </p>
        </header>

        @if ($article->imageUrl())
            <figure class="mx-auto mt-12 w-full max-w-6xl sm:px-6">
                <x-voltiva.image :src="$article->imageUrl()" :alt="$article->title" ratio="aspect-16/9" eager />
                @if (filled($article->image_credit))
                    <figcaption class="mt-2 px-4 text-right text-[11px] text-neutral-400 sm:px-0">
                        {{ $article->image_credit }}
                    </figcaption>
                @endif
            </figure>
        @endif

        <div class="mx-auto w-full max-w-3xl px-4 py-14 sm:px-6">
            @if (app()->getLocale() !== 'en')
                <p class="border-volt-600 mb-8 border-l-2 pl-4 text-sm text-neutral-500">
                    {{ __('This article is currently available in English.') }}
                </p>
            @endif

            <x-voltiva.prose>{!! $article->renderedBody() !!}</x-voltiva.prose>

            {{-- Where this article leads: its topic's section, and its car. --}}
            <aside class="mt-14 border-t border-neutral-200 pt-8">
                <p class="text-sm text-neutral-500">{{ __('Learn more') }}</p>
                <a
                    href="{{ $article->topic->url() }}"
                    class="hover:text-volt-700 mt-2 inline-flex items-center gap-2 text-xl font-medium"
                >
                    {{ __($article->topic->label()) }} <flux:icon.arrow-right />
                </a>
            </aside>
        </div>
    </article>

    @if ($article->vehicle !== null && $article->vehicle->is_published)
        <section class="bg-neutral-50">
            <div class="mx-auto grid w-full max-w-360 items-center gap-10 px-4 py-16 sm:px-6 md:grid-cols-2 lg:px-10">
                <x-voltiva.image
                    :src="$article->vehicle->imageUrl()"
                    :alt="$article->vehicle->name"
                    ratio="aspect-16/10"
                />
                <div>
                    <p class="text-volt-700 text-xs font-medium tracking-[0.2em] uppercase">
                        {{ __('Featured in this article') }}
                    </p>
                    <h2 class="mt-3 text-3xl font-medium tracking-tight">{{ $article->vehicle->name }}</h2>
                    <p class="mt-3 text-lg text-neutral-600">{{ $article->vehicle->tagline }}</p>
                    <p class="mt-4 font-medium">
                        {{ __('From :price', ['price' => $article->vehicle->formattedPrice()]) }}
                    </p>
                    <x-voltiva.button :href="route('cars.show', $article->vehicle)" class="mt-6">
                        {{ __('Explore :name', ['name' => $article->vehicle->name]) }}</x-voltiva.button>
                </div>
            </div>
        </section>
    @endif

    @if ($related->isNotEmpty())
        <section class="mx-auto w-full max-w-360 px-4 py-20 sm:px-6 lg:px-10">
            <x-voltiva.section-heading :eyebrow="__($article->topic->label())" :title="__('More on this topic')" />
            <div class="mt-10 grid gap-x-8 gap-y-12 md:grid-cols-3">
                @foreach ($related as $relatedArticle)
                    <x-voltiva.article-card :article="$relatedArticle" />
                @endforeach
            </div>
        </section>
    @endif

    <x-voltiva.enquiry-section
        :vehicle="$article->vehicle"
        source="register"
        :title="__('Have a question we have not answered?')"
    />
</x-layouts::public>
