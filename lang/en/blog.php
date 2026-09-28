<?php

/*
|--------------------------------------------------------------------------
| Admin Panel: Blog
|--------------------------------------------------------------------------
|
| Strings for the blog's Filament resources. Dotted keys rather than the JSON
| file's English-as-key convention -- see .ai/rules/i18n.md for which applies
| where.
|
| The public-facing copy for the blog is NOT here: it is in lang/en.json with
| the rest of the Blade and Livewire strings.
|
*/

return [

    'posts' => [
        'label' => 'Post',
        'plural_label' => 'Posts',

        'fields' => [
            'title' => 'Title',
            'slug' => 'URL slug',
            'slug_help' => 'The address the post is served at under /blog, e.g. "first-post" for /blog/first-post. Lowercase letters, numbers and hyphens only. Changing it on a published post breaks any link already shared.',
            'slug_reserved' => 'That address is already used by the blog. Choose another.',
            'slug_format' => 'Use lowercase letters, numbers and hyphens only, e.g. "first-post".',
            'body' => 'Content',
            'body_help' => 'Markdown: # for headings, - for lists, [text](url) for links. HTML is shown as plain text rather than rendered.',
            'seo_description' => 'Search description',
            'seo_description_help' => 'A sentence summarising this post for search results, link previews and the post card. Leave blank to use the post\'s opening words.',
            'published_at' => 'Publish date',
            'published_at_help' => 'Leave blank to keep the post as a draft. A date in the past publishes it immediately; a date in the future schedules it to go live then.',
            'category' => 'Category',
            'category_help' => 'The post\'s single grouping. Leave blank for none.',
            'tags' => 'Tags',
            'tags_help' => 'Free-form labels, any number. Managed on the Tags screen.',
        ],

        'table' => [
            'status' => 'Status',
            'author' => 'Author',
            'category' => 'Category',
            'published_at' => 'Publish date',
        ],

        'status' => [
            'draft' => 'Draft',
            'scheduled' => 'Scheduled',
            'published' => 'Published',
        ],

        'filters' => [
            'status' => 'Status',
            'category' => 'Category',
        ],

        'actions' => [
            'view' => 'View',
            'cover' => 'Cover image',
            'cover_upload_heading' => 'Upload a cover image',
            'cover_field' => 'Image',
            'cover_field_help' => 'Re-encoded before it is stored: EXIF data (including GPS) is removed and the image is converted to WebP. Replaces the current cover.',
            'remove_cover' => 'Remove cover image',
            'remove_cover_description' => 'The image and its variants are deleted. The post itself is untouched.',
            'cover_rejected_title' => 'Upload rejected',
            'cover_rejected_body' => 'That file did not come from the upload field. Refresh the page and try again.',
            'cover_uploaded' => 'Cover image uploaded. It will appear on the post shortly.',
            'cover_removed' => 'Cover image removed.',
        ],
    ],

    'categories' => [
        'label' => 'Category',
        'plural_label' => 'Categories',

        'fields' => [
            'name' => 'Name',
            'slug' => 'URL slug',
            'slug_help' => 'The address the category archive is served at under /blog/category. Lowercase letters, numbers and hyphens only.',
            'slug_format' => 'Use lowercase letters, numbers and hyphens only, e.g. "company-news".',
            'posts' => 'Posts',
        ],

        'actions' => [
            'view' => 'View',
        ],
    ],

    'tags' => [
        'label' => 'Tag',
        'plural_label' => 'Tags',

        'fields' => [
            'name' => 'Name',
            'slug' => 'URL slug',
            'slug_help' => 'The address the tag archive is served at under /blog/tag. Lowercase letters, numbers and hyphens only.',
            'slug_format' => 'Use lowercase letters, numbers and hyphens only, e.g. "best-practices".',
            'posts' => 'Posts',
        ],

        'actions' => [
            'view' => 'View',
        ],
    ],

];
