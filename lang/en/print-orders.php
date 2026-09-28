<?php

/*
|--------------------------------------------------------------------------
| Print orders language lines
|--------------------------------------------------------------------------
|
| Filament labels, badges and column headings for the print orders resource
| and the dashboard's print widgets, per the dotted-key convention for panel
| copy. Shared display strings that are not Filament-specific (the customer
| flow, the console) live in lang/en.json as English-source keys instead.
|
*/

return [

    'resource' => [
        'label' => 'Print order',
        'plural_label' => 'Print orders',
    ],

    'fields' => [
        'code' => 'Code',
        'channel' => 'Channel',
        'status' => 'Status',
        'payment_status' => 'Payment',
        'customer_name' => 'Customer',
        'customer_email' => 'Email',
        'customer_phone' => 'Phone',
        'mailing_address' => 'Mailing address',
        'location' => 'Counter',
        'prints' => 'Prints',
        'total' => 'Total',
        'savings' => 'Deal saving',
        'placed' => 'Placed',
        'photos' => 'Photos',
        'quantity' => 'Copies',
        'printer' => 'Printer',
        'printed_at' => 'Printed',
    ],

    'table' => [
        'empty' => 'No orders yet. The QR code on the counter and the website\'s send-photos page both land here.',
    ],

    'overview' => [
        'heading' => 'The counter today',
        'waiting' => 'Waiting to print',
        'printing' => 'Printing now',
        'ready' => 'Ready to hand over',
        'prints_today' => 'Prints today',
        'taken_this_week' => 'Taken this week',
    ],

    'queue' => [
        'heading' => 'The queue',
        'description' => 'Orders still owed work, oldest first. Work them from the fulfillment console.',
    ],

];
