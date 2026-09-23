<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The ISO 4217 code every price is charged and displayed in. Prices are
    | stored as integer cents in this currency; there is no conversion, so
    | changing it relabels every existing price rather than converting it.
    |
    */

    'currency' => env('SHOP_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Low Stock Threshold
    |--------------------------------------------------------------------------
    |
    | A limited package with this many licences or fewer left shows an
    | "only N left" notice to customers and is flagged in the admin panel.
    |
    */

    'low_stock_threshold' => 5,

];
