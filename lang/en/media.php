<?php

/*
|--------------------------------------------------------------------------
| Admin Panel: Media Library
|--------------------------------------------------------------------------
|
| Strings for the Filament media resource. Dotted keys rather than the JSON
| file's English-as-key convention -- see .ai/rules/i18n.md for which applies
| where.
|
| App\Media\MediaCollection's own methods return no copy at all, and must not
| start: the enum is covered by tests/Unit/MediaCollectionTest.php, which has
| no container, so __() there fails outright. Collection labels are translated
| here, at the point of display.
|
*/

return [

    'resource' => [
        'label' => 'File',
        'plural_label' => 'Media library',
    ],

    'collections' => [
        'avatar' => 'Avatar',
        'logo' => 'Logo',
        'page-image' => 'Page image',
        'attachment' => 'Attachment',
    ],

    'fields' => [
        'preview' => 'Preview',
        'file_name' => 'File name',
        'collection' => 'Collection',
        'owner' => 'Attached to',
        'uploader' => 'Uploaded by',
        'mime_type' => 'Type',
        'size' => 'Size',
        'dimensions' => 'Dimensions',
        'uploaded' => 'Uploaded',
        'disk' => 'Disk',
        'path' => 'Stored path',
        'conversions' => 'Conversions',
        'status' => 'Status',
    ],

    'status' => [
        'ready' => 'Ready',
        'processing' => 'Processing',
        'stored' => 'Stored',
    ],

    'filters' => [
        'orphaned' => 'Unattached only',
        'images' => 'Images only',
        'documents' => 'Documents only',
        'from' => 'Uploaded from',
        'until' => 'Uploaded until',
    ],

    'actions' => [
        'download' => 'Download',
    ],

    /*
     * Delete confirmation copy.
     *
     * Deliberately blunt: Media::delete() removes the BYTES as well as the row,
     * an image taking its whole conversion directory with it, and nothing in
     * this application restores them. The records that point at a file -- a
     * page body, an avatar, the site logo -- hold a URL rather than a foreign
     * key, so nothing here can list what is about to break, and a live public
     * page is a perfectly ordinary thing to break this way.
     */
    'delete' => [
        'heading' => 'Delete this file permanently?',
        'heading_bulk' => 'Delete these files permanently?',
        'description' => 'This cannot be undone. The file and every resized version of it are erased from storage immediately — there is no recycle bin and no backup to restore from.',
        'consequences' => 'Anything still using this file will break. Pages that show it will display a broken image to visitors, and an avatar or logo will vanish from the site. That damage is not shown here and is not reversible by re-uploading, because the new upload gets a different address.',
        'confirm' => 'Delete permanently',
        'confirm_bulk' => 'Delete all permanently',
    ],

    'empty' => [
        'heading' => 'No files yet',
        'description' => 'Avatars, the site logo and any attachments appear here once they are uploaded.',
    ],

    'unknown_owner' => '(type no longer exists)',

    'orphan_warning' => 'Not attached to any record. Scheduled for automatic deletion.',

];
