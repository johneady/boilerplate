{{--
    News & Advice: every published article, newest first, filterable by the
    topic it links back to. $articles is paginated; $topic is the active
    filter or null.
--}}
<x-layouts::public
    :title="$topic === null ? __('News & Advice') : __(':topic – News & Advice', ['topic' => __($topic->label())])"
    :description="__('Straightforward advice on electric cars in Mallorca: batteries, range, charging, registration, servicing and finance.')"
>
    <section class="mx-auto w-full max-w-360 px-4 pt-16 pb-24 sm:px-6 lg:px-10 lg:pt-24">
        <x-voltiva.section-heading
            as="h1"
            :eyebrow="__('News & Advice')"
            :title="__('Straight answers about electric cars.')"
            :intro="__('Plain-English guides to owning a small electric car in Mallorca – written by the people who sell and service them.')"
        />

        <nav aria-label="{{ __('Topics') }}" class="mt-10 flex flex-wrap gap-2">
            <a
                href="{{ route('news.index') }}"
                @class(['border px-4 py-2 text-sm transition', 'border-neutral-950 bg-neutral-950 text-white' => $topic === null, 'border-neutral-300 hover:border-neutral-950' => $topic !== null])
            >{{ __('All topics') }}</a>
            @foreach (\App\Voltiva\ArticleTopic::cases() as $case)
                <a
                    href="{{ route('news.index', ['topic' => $case->value]) }}"
                    @class(['border px-4 py-2 text-sm transition', 'border-neutral-950 bg-neutral-950 text-white' => $topic === $case, 'border-neutral-300 hover:border-neutral-950' => $topic !== $case])
                >{{ __($case->label()) }}</a>
            @endforeach
        </nav>

        @if ($articles->isEmpty())
            <p class="mt-16 text-neutral-600">{{ __('No articles on this topic yet.') }}</p>
        @else
            <div class="mt-12 grid gap-x-8 gap-y-16 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <x-voltiva.article-card :article="$article" />
                @endforeach
            </div>

            <div class="mt-16">{{ $articles->links() }}</div>
        @endif
    </section>
</x-layouts::public>
