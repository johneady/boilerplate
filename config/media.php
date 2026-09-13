<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Orphan Retention
    |--------------------------------------------------------------------------
    |
    | How long (in hours) a media row with no owning record is kept before
    | app:prune-orphaned-media collects it and deletes its files.
    |
    | This is a GRACE PERIOD, not a cleanup delay. A row is written before its
    | owner exists, which is what lets a create form hold an uploaded file
    | before the record is saved -- so an unattached row is indistinguishable
    | from one whose form is still open in somebody's browser. Too short a
    | window deletes the upload out from under a user who is still typing.
    |
    | Set to 0 to disable pruning entirely.
    |
    */

    'orphan_retention_hours' => (int) env('MEDIA_ORPHAN_RETENTION_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Accepted Documents
    |--------------------------------------------------------------------------
    |
    | Extensions accepted for non-image collections, and the ceiling in
    | kilobytes.
    |
    | These files are stored AS UPLOADED -- nothing re-encodes them the way
    | App\Jobs\ProcessUploadedImage re-encodes an image -- so they are served
    | only from the private disk through a signed route, never as a static URL.
    | Keep anything the browser executes in our origin off this list: .html,
    | .svg and .xml are documents a browser will happily run script from.
    |
    */

    'accepted_document_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip'],

    'max_document_kilobytes' => (int) env('MEDIA_MAX_DOCUMENT_KILOBYTES', 10240),

];
