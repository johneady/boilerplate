<?php

/*
|--------------------------------------------------------------------------
| Admin Panel: Perfumes
|--------------------------------------------------------------------------
|
| Strings for the perfume catalogue and data refresh screens. The public
| site's copy is in lang/en.json -- see .ai/rules/i18n.md.
|
*/

return [

    'navigation_group' => 'Perfume database',

    'perfumes' => [
        'label' => 'Perfume',
        'plural_label' => 'Perfumes',

        'fields' => [
            'name' => 'Name',
            'brand' => 'House',
            'external_id' => 'Source ID',
            'external_id_help' => 'The record\'s key in your data files. Refreshes match rows on it, so keep it stable.',
            'slug' => 'URL slug',
            'release_year' => 'Released',
            'concentration' => 'Concentration',
            'gender' => 'Marketed to',
            'family' => 'Family',
            'perfumer' => 'Perfumer',
            'top_notes' => 'Top notes',
            'heart_notes' => 'Heart notes',
            'base_notes' => 'Base notes',
            'description' => 'Description',
            'followers' => 'Followers',
            'updated_at' => 'Last updated',
        ],

        'actions' => [
            'view' => 'View on site',
        ],
    ],

    'imports' => [
        'label' => 'Data refresh',
        'plural_label' => 'Data refreshes',
        'description' => 'Upload a CSV export to add new perfumes and update existing ones. Rows are matched on their source ID, so followers and view history are never lost. Nothing is deleted.',

        'fields' => [
            'file_name' => 'File',
            'status' => 'Status',
            'rows_total' => 'Rows',
            'rows_created' => 'Added',
            'rows_updated' => 'Updated',
            'rows_unchanged' => 'Unchanged',
            'rows_failed' => 'Failed',
            'user' => 'Run by',
            'finished_at' => 'Finished',
            'errors' => 'Problems',
            'file' => 'CSV file',
            'file_help' => 'Required columns: external_id, brand, name. Optional: brand_country, release_year, concentration, gender, family, perfumer, top_notes, heart_notes, base_notes (separate notes with |), description.',
        ],

        'actions' => [
            'run' => 'Upload a refresh',
            'run_submit' => 'Import',
            'sample' => 'Download sample file',
        ],

        'notifications' => [
            'finished' => 'Refresh finished: :created added, :updated updated, :unchanged unchanged.',
            'failed_rows' => ':count row(s) could not be imported. Open the refresh to see which.',
            'failed' => 'The file could not be imported.',
        ],

        'scheduled' => 'Automatic nightly refresh: :state',
        'scheduled_on' => 'on, from the configured feed',
        'scheduled_off' => 'off (set PERFUME_FEED_URL to enable)',
        'system' => 'Scheduled',
        'no_errors' => 'No problems.',
        'line' => 'Line',
        'problem' => 'Problem',
    ],

];
