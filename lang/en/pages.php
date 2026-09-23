<?php

/*
|--------------------------------------------------------------------------
| Admin Panel: Content Pages
|--------------------------------------------------------------------------
|
| Strings for the Filament page and contact submission resources. Dotted keys
| rather than the JSON file's English-as-key convention -- see .ai/rules/i18n.md
| for which applies where.
|
| The public-facing copy for these pages is NOT here: it is in lang/en.json with
| the rest of the Blade and Livewire strings.
|
*/

return [

    'resource' => [
        'label' => 'Page',
        'plural_label' => 'Pages',
    ],

    'fields' => [
        'title' => 'Title',
        'slug' => 'URL slug',
        'slug_help' => 'The address the page is served at, e.g. "privacy" for /privacy. Lowercase letters, numbers and hyphens only. Changing it on a published page breaks any link already shared.',
        'slug_reserved' => 'That address is already used by the application. Choose another.',
        'slug_format' => 'Use lowercase letters, numbers and hyphens only, e.g. "cookie-policy".',
        'body' => 'Content',
        'body_help' => 'Markdown: # for headings, - for lists, [text](url) for links. HTML is shown as plain text rather than rendered.',
        'seo_description' => 'Search description',
        'seo_description_help' => 'A sentence summarising this page for search results and link previews. Leave blank to use the site-wide description.',
        'image' => 'Header photo',
        'image_help' => 'Optional. A wide photo shown above the title, cropped to fit -- any size or shape works.',
        'is_published' => 'Published',
        'is_published_help' => 'When off, only editors can reach the page.',
        'show_in_footer' => 'Link in footer',
        'show_in_footer_help' => 'Link to this page from the site footer.',
        'sort_order' => 'Footer order',
        'updated' => 'Last updated',
    ],

    'reorder_note' => [
        'heading' => 'Footer order is set by dragging',
        'description' => 'Use the reorder button above, then drag rows into the order you want. That order is the order the links appear in the public site\'s footer, left to right. Only pages that are published and set to link in the footer appear there. Clear any search or filter first — reordering is only available when every page is in view.',
    ],

    'actions' => [
        'view' => 'View',
        'upload_image' => 'Add image',
    ],

    'submissions' => [
        'label' => 'Contact message',
        'plural_label' => 'Contact messages',

        // The sidebar line, shorter than the plural label: in the panel's
        // Content group the "contact" half adds no information.
        'navigation_label' => 'Messages',

        'fields' => [
            'name' => 'From',
            'email' => 'Email address',
            'subject' => 'Subject',
            'no_subject' => 'No subject',
            'message' => 'Message',
            'received' => 'Received',
            'handled' => 'Handled',
            'ip_address' => 'IP address',
            'user_agent' => 'Browser',
        ],

        'actions' => [
            'mark_handled' => 'Mark handled',
            'mark_unhandled' => 'Mark unhandled',
        ],
    ],

    'images' => [
        'heading' => 'Add an image',
        'description' => 'The image is re-encoded before it is stored, which removes camera metadata such as GPS coordinates. Copy its address from the media library to place it in the page body.',
        'field' => 'Image',
        'uploaded' => 'Image uploaded',
        'uploaded_body' => 'It will finish processing shortly and appear in the media library.',
        'rejected' => 'That upload could not be accepted.',
    ],

];
