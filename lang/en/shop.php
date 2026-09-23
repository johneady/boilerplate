<?php

/*
|--------------------------------------------------------------------------
| Admin Panel: Shop
|--------------------------------------------------------------------------
|
| Strings for the Filament package and order resources. Dotted keys rather
| than the JSON file's English-as-key convention -- see .ai/rules/i18n.md.
|
| The storefront copy is NOT here: it is in lang/en.json with the rest of the
| Blade and Livewire strings.
|
*/

return [

    'packages' => [
        'label' => 'Package',
        'plural_label' => 'Packages',
        'sold_out_badge' => 'Packages on sale that have sold out',

        'sections' => [
            'listing' => 'Listing',
            'footage' => 'Footage',
            'availability' => 'Stock and availability',
            'image' => 'Cover image',
        ],

        'fields' => [
            'title' => 'Title',
            'slug' => 'URL slug',
            'slug_help' => 'The package is served at /videos/{slug}. Lowercase letters, numbers and hyphens only.',
            'location' => 'Location',
            'country' => 'Country',
            'region' => 'Region',
            'price' => 'Price',
            'summary' => 'Summary',
            'summary_help' => 'One or two sentences shown on the package card and in search results.',
            'description' => 'Description',
            'description_help' => 'Markdown: - for lists, **bold**. HTML is shown as plain text rather than rendered.',
            'resolution' => 'Resolution',
            'frame_rate' => 'Frame rate',
            'clip_count' => 'Clips',
            'duration' => 'Running time',
            'seconds' => 'seconds',
            'stock' => 'Licences left',
            'stock_help' => 'Leave blank to sell without limit. 0 shows the package as sold out.',
            'unlimited' => 'Unlimited',
            'sold' => 'Sold',
            'is_active' => 'On sale',
            'is_active_help' => 'When off, the package is hidden from the storefront.',
            'is_featured' => 'Featured',
            'is_featured_help' => 'Shown first in the catalogue and on the home page.',
            'image' => 'Cover image',
            'image_credit' => 'Image credit',
            'image_credit_help' => 'Shown under the image on the product page, e.g. "Photo: Jane Doe".',
        ],

        'filters' => [
            'low_stock' => 'Low or sold out',
        ],

        'actions' => [
            'view' => 'View in store',
        ],
    ],

    'orders' => [
        'label' => 'Order',
        'plural_label' => 'Orders',

        'fields' => [
            'reference' => 'Order',
            'status' => 'Status',
            'customer' => 'Customer',
            'email' => 'Email',
            'placed' => 'Placed',
            'fulfilled' => 'Fulfilled',
            'items' => 'Packages',
            'total' => 'Total',
        ],

        'actions' => [
            'fulfil' => 'Mark fulfilled',
            'refund' => 'Refund',
            'refund_confirm' => 'The order is marked refunded and its licences go back into stock. Refund the payment itself from your payment provider.',
        ],

        'notifications' => [
            'fulfilled' => 'Order marked as fulfilled',
            'refunded' => 'Order refunded and stock restored',
        ],
    ],

];
