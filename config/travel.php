<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Every tour price is stored in cents of this currency. Shown on the site as
    | "per person", the way tour operators quote.
    |
    */

    'currency' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | Child Pricing
    |--------------------------------------------------------------------------
    |
    | Children travelling with an adult pay this percentage of the adult price.
    | "Child" means under the age below on the day the tour starts.
    |
    */

    'child_price_percent' => 75,

    'child_max_age' => 11,

    /*
    |--------------------------------------------------------------------------
    | Deposit
    |--------------------------------------------------------------------------
    |
    | The share of the quoted total due to secure seats once a booking request
    | is confirmed. The balance is due before departure.
    |
    */

    'deposit_percent' => 20,

    'balance_due_days' => 60,

    /*
    |--------------------------------------------------------------------------
    | Party Size
    |--------------------------------------------------------------------------
    |
    | The largest party the public form accepts. Bigger groups are a private
    | departure, quoted by hand.
    |
    */

    'max_party_size' => 8,

    /*
    |--------------------------------------------------------------------------
    | Low Availability
    |--------------------------------------------------------------------------
    |
    | A departure with this many seats or fewer left is flagged "Only N left".
    |
    */

    'few_seats_threshold' => 4,

];
