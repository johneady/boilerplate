{{--
    Body text goes in :description, never in the default slot -- the callout
    component renders only heading/description/footer and silently drops slot
    content, so it would still look styled and correct with the note missing.
    See .ai/rules/views-filament.md.
--}}
<x-filament::callout
    color="gray"
    icon="heroicon-o-arrows-up-down"
    :heading="__('pages.reorder_note.heading')"
    :description="__('pages.reorder_note.description')"
/>
