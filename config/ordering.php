<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Online Ordering
    |--------------------------------------------------------------------------
    |
    | The café's ordering rules. Amounts are in cents of the currency below.
    | Opening hours are in the café's own timezone (the Timezone setting).
    |
    */

    'currency' => 'CAD',

    // The locale prices are formatted in, so CAD reads as "$4.25".
    'locale' => 'en_CA',

    'order_number_prefix' => 'JR-',

    'delivery_fee_cents' => 500,

    'free_delivery_from_cents' => 4000,

    // How long the kitchen needs before the first available time slot.
    'preparation_minutes' => 30,

    'opens_at' => '07:30',

    'closes_at' => '17:30',

];
