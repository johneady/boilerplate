<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feed URL
    |--------------------------------------------------------------------------
    |
    | A CSV the nightly `perfumes:import` run pulls and upserts, such as a
    | published Google Sheet or an export endpoint. Leave it unset to refresh
    | only by uploading files in the admin panel.
    |
    */

    'feed_url' => env('PERFUME_FEED_URL'),

];
