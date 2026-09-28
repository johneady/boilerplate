<?php

/*
|--------------------------------------------------------------------------
| Print locations language lines
|--------------------------------------------------------------------------
|
| Filament labels for the print locations resource: the counters whose QR
| codes send customers into the in-store flow.
|
*/

return [

    'resource' => [
        'label' => 'Counter',
        'plural_label' => 'Counters & QR codes',
    ],

    'fields' => [
        'name' => 'Counter name',
        'slug' => 'QR code URL',
        'slug_helper' => 'The QR code encodes /photo/this-slug. Keep it short: it prints as text beneath the code for anyone whose camera will not scan.',
        'address' => 'Address',
        'address_helper' => 'Shown on the customer\'s phone when they scan, and on their confirmation.',
        'is_active' => 'Active',
        'is_active_helper' => 'A retired counter\'s QR code answers "not found" instead of quietly sending orders nobody is standing there to receive.',
        'orders' => 'Orders',
        'qr' => 'QR code',
    ],

    'qr' => [
        'show' => 'Show QR code',
        'heading' => 'Scan to order prints',
        'description' => 'Print this, tape it beside the counter. The code opens the photo flow branded to :name, with no payment step — customers pick up and pay at the counter.',
        'url_note' => 'Encodes :url',
    ],

];
