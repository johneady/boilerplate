{{--
    An administrator-authored content page: the "Why Voltiva" pages
    (Batteries & Range, Charging, Registration...), About, FAQ, and the
    policies. One template for all of them, so an editor changes the words
    and the photo and never the design.

    The body is Markdown rendered by Page::renderedBody(), which escapes any
    HTML in the source -- that method's docblock explains why the {!! !!}
    below is safe and what must not be removed from it.

    $topic and $articles come from PageController: a page that belongs to an
    article topic lists the latest advice written on it.
--}}
@php
    // The pages where the jargon buster earns its place beneath the text.
    $showsGlossary = in_array($page->slug, ['faq', 'batteries-and-range', 'charging', 'registration'], true);
    // Sales pages end with the enquiry form; the policies do not.
    $showsEnquiry = $topic !== null || in_array($page->slug, ['about', 'faq', 'accessories'], true);
@endphp

<x-layouts::public :title="$page->title" :description="$page->seo_description" :image="$page->imageUrl()">
    <article>
        @if ($page->imageUrl())
            <div class="relative isolate">
                <x-voltiva.image :src="$page->imageUrl()" alt="" ratio="aspect-4/3 sm:aspect-21/9" eager />
                <div class="absolute inset-0 bg-linear-to-t from-neutral-950/70 via-neutral-950/10 to-transparent"></div>
                <header class="absolute inset-x-0 bottom-0 mx-auto max-w-360 px-4 pb-10 text-white sm:px-6 lg:px-10 lg:pb-14">
                    <h1 class="max-w-3xl text-4xl font-medium tracking-tight text-balance sm:text-6xl">
                        {{ $page->title }}
                    </h1>
                    @if (filled($page->seo_description))
                        <p class="mt-4 max-w-2xl text-lg text-white/85">{{ $page->seo_description }}</p>
                    @endif
                </header>
            </div>
        @else
            <header class="mx-auto w-full max-w-3xl px-4 pt-16 sm:px-6 lg:pt-24">
                <h1 class="text-4xl font-medium tracking-tight text-balance sm:text-5xl">{{ $page->title }}</h1>
            </header>
        @endif

        <div class="mx-auto w-full max-w-3xl px-4 py-14 sm:px-6 lg:py-20">
            @unless ($page->is_published)
                <div class="mb-8 border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {{ __('This page is a draft. Only people who can edit it see this.') }}
                </div>
            @endunless

            @if (app()->getLocale() !== 'en' && ! in_array($page->slug, ['privacy', 'terms'], true))
                <p class="border-volt-600 mb-8 border-l-2 pl-4 text-sm text-neutral-500">
                    {{ __('This page is currently available in English.') }}
                </p>
            @endif

            <x-voltiva.prose>{!! $page->renderedBody() !!}</x-voltiva.prose>

            @if ($page->updated_at !== null)
                <p class="mt-12 text-sm text-neutral-500">
                    {{ __('Last updated :date', ['date' => app(\App\Settings\Settings::class)->formatDate($page->updated_at)]) }}
                </p>
            @endif
        </div>
    </article>

    @if ($showsGlossary)
        <section class="bg-neutral-50">
            <div class="mx-auto w-full max-w-360 px-4 py-20 sm:px-6 lg:px-10">
                <x-voltiva.section-heading
                    :eyebrow="__('Jargon buster')"
                    :title="__('The technical terms, in plain English')"
                />
                <dl class="mt-12 grid gap-x-10 gap-y-10 md:grid-cols-2 lg:grid-cols-3">
                    @foreach (\App\Voltiva\Glossary::all() as $entry)
                        <div class="border-t border-neutral-300 pt-5">
                            <dt class="font-medium">{{ __($entry['title']) }}</dt>
                            <dd class="mt-2 text-sm leading-relaxed text-neutral-600">
                                {{ __($entry['explanation']) }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>
    @endif

    @if ($articles->isNotEmpty())
        <section class="mx-auto w-full max-w-360 px-4 py-20 sm:px-6 lg:px-10">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <x-voltiva.section-heading
                    :eyebrow="__('News & Advice')"
                    :title="__('More about :topic', ['topic' => __($topic->label())])"
                />
                <a
                    href="{{ route('news.index', ['topic' => $topic->value]) }}"
                    class="hover:text-volt-700 text-sm font-medium"
                >{{ __('All articles on this topic') }}</a>
            </div>
            <div class="mt-10 grid gap-x-8 gap-y-12 md:grid-cols-3">
                @foreach ($articles as $article)
                    <x-voltiva.article-card :article="$article" />
                @endforeach
            </div>
        </section>
    @endif

    @if ($showsEnquiry)
        <x-voltiva.enquiry-section source="register" />
    @endif
</x-layouts::public>
