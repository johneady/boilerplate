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

    'empty' => [
        'heading' => 'No files yet',
        'description' => 'Avatars, the site logo and any attachments appear here once they are uploaded.',
    ],

    'orphan_warning' => 'Not attached to any record. Scheduled for automatic deletion.',

];
