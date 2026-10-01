<?php

/*
|--------------------------------------------------------------------------
| Admin Panel: Travel
|--------------------------------------------------------------------------
|
| Strings for the destination, tour and booking request resources. Dotted
| keys rather than the JSON file's English-as-key convention -- see
| .ai/rules/i18n.md. The public site's copy is in lang/en.json.
|
*/

return [

    'navigation_group' => 'Travel',

    'destinations' => [
        'label' => 'Destination',
        'plural_label' => 'Destinations',
        'fields' => [
            'name' => 'Name',
            'slug' => 'URL slug',
            'slug_help' => 'The address of the destination page, e.g. "iceland" for /destinations/iceland.',
            'country' => 'Country',
            'region' => 'Region',
            'tagline' => 'Tagline',
            'description' => 'Description',
            'description_help' => 'Leave a blank line between paragraphs.',
            'best_time' => 'Best time to go',
            'is_featured' => 'Featured',
            'tours' => 'Tours',
            'photo' => 'Photo',
            'image_credit' => 'Photo credit',
        ],
        'actions' => [
            'view' => 'View on site',
        ],
    ],

    'tours' => [
        'label' => 'Tour',
        'plural_label' => 'Tours',
        'sections' => [
            'basics' => 'The tour',
            'pricing' => 'Price and group',
            'content' => 'Page content',
        ],
        'fields' => [
            'name' => 'Name',
            'slug' => 'URL slug',
            'destination' => 'Destination',
            'style' => 'Travel style',
            'summary' => 'Summary',
            'summary_help' => 'One sentence, shown on cards and in search results.',
            'description' => 'Overview',
            'description_help' => 'Leave a blank line between paragraphs.',
            'duration_days' => 'Days',
            'group_size_max' => 'Max group size',
            'price' => 'Price per person',
            'price_help' => 'Twin share. A departure can override it.',
            'single_supplement' => 'Solo supplement',
            'single_supplement_help' => 'Added when one adult travels alone.',
            'highlights' => 'Highlights',
            'inclusions' => 'What\'s included',
            'itinerary' => 'Itinerary',
            'day' => 'Day(s)',
            'day_title' => 'Title',
            'day_body' => 'Description',
            'is_featured' => 'Bestseller',
            'is_published' => 'Published',
            'next_departure' => 'Next departure',
            'photo' => 'Photo',
        ],
        'actions' => [
            'view' => 'View on site',
        ],
    ],

    'departures' => [
        'title' => 'Departure dates',
        'fields' => [
            'starts_on' => 'Starts',
            'price' => 'Price per person',
            'price_help' => 'Leave blank to use the tour\'s price.',
            'seats_total' => 'Seats',
            'seats_held' => 'Seats taken',
            'seats_held_help' => 'Confirmed bookings add to this automatically.',
            'seats_left' => 'Left',
        ],
    ],

    'inquiries' => [
        'label' => 'Booking request',
        'plural_label' => 'Booking requests',
        'navigation_label' => 'Bookings',
        'sections' => [
            'trip' => 'Trip',
            'traveller' => 'Traveller',
        ],
        'fields' => [
            'reference' => 'Reference',
            'status' => 'Status',
            'trip' => 'Trip',
            'departure' => 'Departure',
            'travel_month' => 'Travelling',
            'party' => 'Party',
            'party_value' => ':adults adults, :children children',
            'quote' => 'Quoted',
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'message' => 'Notes',
            'received' => 'Received',
            'confirmed_at' => 'Confirmed',
            'seats_left' => 'Seats left on departure',
            'custom' => 'Tailor-made',
            'type' => 'Type',
        ],
        'actions' => [
            'contacted' => 'Mark contacted',
            'confirm' => 'Confirm booking',
            'confirm_description' => 'This holds :count seats on the departure.',
            'confirmed' => 'Booking confirmed and seats held.',
            'decline' => 'Decline',
            'decline_description' => 'Any seats this request holds are released.',
            'declined' => 'Request declined.',
            'reply' => 'Email traveller',
        ],
    ],

    'dashboard' => [
        'heading' => 'Bookings at a glance',
        'new' => 'New requests',
        'new_description' => ':count received in the last 7 days',
        'pipeline' => 'Open pipeline',
        'pipeline_description' => 'Quoted value of new and contacted requests',
        'confirmed' => 'Confirmed, last 30 days',
        'confirmed_description' => ':count bookings',
        'occupancy' => 'Seats sold, next 90 days',
        'occupancy_description' => ':held of :total seats on :departures departures',
        'upcoming' => 'Upcoming departures',
    ],

];
