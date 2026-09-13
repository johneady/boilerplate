{{--
    An administrator-authored content page: the privacy policy, the terms, about.

    The body is Markdown rendered by Page::renderedBody(), which escapes any HTML
    in the source -- that method's docblock explains why the {!! !!} below is safe
    and what must not be removed from it.

    Element styling is applied from this wrapper with descendant selectors rather
    than by classing the body's own tags, because the body's HTML comes from
    Markdown and carries no classes. @tailwindcss/typography would do the same
    job; it is not installed, and a dependency is not added without approval.
--}}
<x-layouts::public :title="$page->title" :description="$page->seo_description">
    <main class="flex-1 py-12">
        <article class="mx-auto max-w-3xl">
            <header>
                <h1 class="text-4xl font-semibold tracking-tight text-balance">{{ $page->title }}</h1>

                @if ($page->updated_at !== null)
                    <p class="mt-4 text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Last updated :date', ['date' => app(\App\Settings\Settings::class)->formatDate($page->updated_at)]) }}
                    </p>
                @endif
            </header>

            @unless ($page->is_published)
                <div class="mt-8 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                    {{ __('This page is a draft. Only people who can edit it see this.') }}
                </div>
            @endunless

            <div class="[&_a]:font-medium [&_a]:text-sky-700 [&_a]:underline [&_a]:underline-offset-2 dark:[&_a]:text-sky-400 [&_blockquote]:my-6 [&_blockquote]:border-l-2 [&_blockquote]:border-neutral-300 [&_blockquote]:pl-4 [&_blockquote]:text-neutral-600 dark:[&_blockquote]:border-neutral-700 dark:[&_blockquote]:text-neutral-400 [&_code]:rounded [&_code]:bg-neutral-100 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:text-sm dark:[&_code]:bg-neutral-800 [&_h2]:mt-12 [&_h2]:mb-4 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_h2]:text-neutral-900 dark:[&_h2]:text-neutral-100 [&_h3]:mt-8 [&_h3]:mb-3 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-neutral-900 dark:[&_h3]:text-neutral-100 [&_h4]:mt-6 [&_h4]:mb-2 [&_h4]:text-lg [&_h4]:font-semibold [&_h4]:text-neutral-900 dark:[&_h4]:text-neutral-100 [&_hr]:my-10 [&_hr]:border-neutral-200 dark:[&_hr]:border-neutral-800 [&_li]:my-1 [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-4 [&_pre]:my-6 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-neutral-100 [&_pre]:p-4 [&_pre]:text-sm dark:[&_pre]:bg-neutral-900 [&_strong]:font-semibold [&_strong]:text-neutral-900 dark:[&_strong]:text-neutral-100 [&_table]:my-6 [&_table]:w-full [&_table]:text-left [&_table]:text-sm [&_td]:border-t [&_td]:border-neutral-200 [&_td]:py-2 [&_td]:pr-4 dark:[&_td]:border-neutral-800 [&_th]:border-b [&_th]:border-neutral-300 [&_th]:py-2 [&_th]:pr-4 [&_th]:font-semibold dark:[&_th]:border-neutral-700 [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6 mt-10 text-base leading-relaxed text-neutral-700 dark:text-neutral-300">
                {!! $page->renderedBody() !!}
            </div>
        </article>
    </main>
</x-layouts::public>
