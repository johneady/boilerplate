<?php

return [

    'navigation_group' => 'Online orders',

    'products' => [
        'label' => 'product',
        'plural_label' => 'Menu',
        'unavailable_badge' => 'Products switched off (sold out)',
        'sections' => [
            'product' => 'Product',
            'photo' => 'Photo',
        ],
        'fields' => [
            'name' => 'Name',
            'slug' => 'URL slug',
            'category' => 'Menu section',
            'description' => 'Description',
            'price' => 'Price',
            'is_available' => 'On the menu',
            'is_available_help' => 'Switch off when sold out. It disappears from the menu and from customers\' carts.',
            'is_featured' => 'Featured',
            'is_featured_help' => 'Shown in the photo grid at the top of the home page (up to three).',
            'image' => 'Photo',
            'image_credit' => 'Photo credit',
            'image_credit_help' => 'Only needed for photos under a licence that asks for credit.',
        ],
    ],

    'orders' => [
        'label' => 'order',
        'plural_label' => 'Orders',
        'open_badge' => 'New orders waiting to be started',
        'fields' => [
            'number' => 'Order',
            'customer' => 'Customer',
            'phone' => 'Phone',
            'email' => 'Email',
            'fulfilment' => 'Pickup or delivery',
            'address' => 'Delivery address',
            'ready_at' => 'Wanted for',
            'status' => 'Status',
            'items' => 'Items',
            'quantity' => 'Qty',
            'product' => 'Product',
            'unit_price' => 'Each',
            'line_total' => 'Line total',
            'subtotal' => 'Subtotal',
            'delivery_fee' => 'Delivery',
            'total' => 'Total',
            'notes' => 'Customer notes',
            'placed_at' => 'Placed',
        ],
        'tabs' => [
            'active' => 'In progress',
            'new' => 'New',
            'ready' => 'Ready',
            'finished' => 'Finished',
            'all' => 'All',
        ],
        'sections' => [
            'items' => 'What they ordered',
            'customer' => 'Customer',
        ],
        'actions' => [
            'cancel' => 'Cancel order',
            'cancel_confirm' => 'The customer\'s order page will show it as cancelled.',
            'track' => 'Customer\'s view',
        ],
        'advanced' => 'Order :number is now :status.',
        'cancelled' => 'Order :number was cancelled.',
    ],

    'widgets' => [
        'today_orders' => 'Orders today',
        'today_orders_description' => ':count still in the kitchen',
        'today_sales' => 'Sales today',
        'today_sales_description' => 'Excludes cancelled orders',
        'average_order' => 'Average order (7 days)',
        'average_order_description' => 'Across :count orders',
        'queue_heading' => 'Kitchen queue',
        'queue_description' => 'Open orders, soonest first. Click the button to move an order along.',
        'queue_empty' => 'Nothing waiting. Enjoy the quiet.',
    ],

];
