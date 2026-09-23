<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The ISO 4217 code menu prices are shown in. Prices are stored as integer
    | cents in this currency; there is no conversion, so changing it relabels
    | every existing price rather than converting it.
    |
    */

    'currency' => env('BAKERY_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Minimum Notice
    |--------------------------------------------------------------------------
    |
    | The fewest days ahead any order can be asked for, whatever is on it. An
    | item that needs longer (a celebration cake, a long-fermented loaf)
    | raises the earliest date offered through its own notice_days.
    |
    */

    'minimum_notice_days' => 2,

    /*
    |--------------------------------------------------------------------------
    | Order Limits
    |--------------------------------------------------------------------------
    |
    | How far ahead an order can be placed, how many lines one inquiry can
    | carry and the most of one item on a line. A home kitchen has a real
    | capacity; anything larger is a conversation, not a form.
    |
    */

    'booking_window_days' => 120,

    'max_lines' => 8,

    'max_quantity' => 24,

];
