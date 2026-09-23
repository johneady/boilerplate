{{--
    Typography for Markdown rendered by the models (page bodies, articles,
    car descriptions), which carries no classes of its own -- so it is styled
    from this wrapper with descendant selectors. The HTML is escaped by the
    model before it arrives; see Page::renderedBody().
--}}
<div {{ $attributes->class('text-[17px] leading-relaxed text-neutral-700 [&_a]:font-medium [&_a]:text-volt-700 [&_a]:underline [&_a]:underline-offset-2 [&_blockquote]:my-6 [&_blockquote]:border-l-2 [&_blockquote]:border-volt-600 [&_blockquote]:pl-4 [&_blockquote]:text-neutral-600 [&_h2]:mt-12 [&_h2]:mb-4 [&_h2]:text-2xl [&_h2]:font-medium [&_h2]:tracking-tight [&_h2]:text-neutral-950 [&_h3]:mt-8 [&_h3]:mb-2 [&_h3]:text-lg [&_h3]:font-medium [&_h3]:text-neutral-950 [&_li]:my-1.5 [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-4 [&_strong]:font-medium [&_strong]:text-neutral-950 [&_table]:my-6 [&_table]:w-full [&_table]:text-left [&_table]:text-sm [&_td]:border-t [&_td]:border-neutral-200 [&_td]:py-2 [&_td]:pr-4 [&_th]:border-b [&_th]:border-neutral-300 [&_th]:py-2 [&_th]:pr-4 [&_th]:font-medium [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6 [&>*:first-child]:mt-0') }}>
    {{ $slot }}
</div>
