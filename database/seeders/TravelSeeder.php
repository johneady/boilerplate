<?php

namespace Database\Seeders;

use App\Auth\DevLoginAccounts;
use App\Models\Destination;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TripInquiry;
use App\Settings\SettingKey;
use App\Travel\InquiryStatus;
use App\Travel\TripQuote;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Wanderlight Travel: the brand, the destinations, the tours and their
 * departures, plus sample booking requests so the admin panel has a pipeline.
 *
 * Runs on every container boot, so it must be idempotent and must not use
 * factories -- faker is absent from the --no-dev production image. See
 * .ai/rules/seeders.md. Everything is firstOrCreate on a slug or key, so the
 * agency's own edits survive a redeploy; departures and sample requests are
 * written only into an empty table.
 *
 * Runs BEFORE SettingsSeeder, so the agency's details land ahead of the
 * generic placeholders.
 *
 * Departure dates are relative to the day the seeder runs, so a demo seeded
 * months from now still shows upcoming dates.
 *
 * The photos ship beside this seeder in images/travel and are copied onto the
 * public disk, where uploads made in the admin panel also live. Each is
 * credited in its destination's image_credit, as the licences require.
 */
class TravelSeeder extends Seeder
{
    /**
     * The agency's brand and contact details, keyed by SettingKey value.
     *
     * The blog and payments are switched off explicitly: neither is part of
     * this site, and a stored "off" stops their own seeders switching them on.
     *
     * @var array<string, mixed>
     */
    private const array SETTINGS = [
        'business_name' => 'Wanderlight Travel',
        'business_address' => "Suite 410, 88 Harbour Street\nToronto, ON M5J 2G2",
        'business_phone' => '+1 (416) 555-0142',
        'business_email' => 'trips@wanderlight.example',
        'seo_title' => 'Wanderlight Travel — Small-group tours & tailor-made trips',
        'seo_description' => 'Guided small-group tours to Greece, Italy, Iceland, South Africa, Alaska and more. Browse dates and prices, then request to book in two minutes.',
        'timezone' => 'America/Toronto',
        'blog_enabled' => false,
        'payments_enabled' => false,
    ];

    /**
     * @var list<array<string, mixed>>
     */
    private const array DESTINATIONS = [
        [
            'slug' => 'santorini', 'name' => 'Santorini', 'country' => 'Greece', 'region' => 'europe',
            'tagline' => 'Whitewashed villages on the rim of a drowned volcano.',
            'description' => "Santorini is what's left of a volcano that blew itself apart 3,600 years ago, and its villages cling to the cliffs of the flooded caldera. Mornings are for walking the rim path from Fira to Oia before the day-trippers arrive; evenings are for watching the sun drop into the Aegean from a quiet terrace.\n\nOur guides take you beyond the postcard: to Bronze Age Akrotiri, to family wineries growing assyrtiko in basket-woven vines, and on to Naxos, the greener, gentler island most visitors never see.",
            'best_time' => 'Late April to early June, and September to mid-October: warm sea, fewer crowds.',
            'image' => 'santorini-caldera.jpg', 'image_credit' => 'Photo: Anna Tsolidou, CC BY-SA 4.0, via Wikimedia Commons',
            'is_featured' => true,
        ],
        [
            'slug' => 'tuscany', 'name' => 'Tuscany', 'country' => 'Italy', 'region' => 'europe',
            'tagline' => 'Cypress lanes, hill towns and long lunches.',
            'description' => "Tuscany rewards travellers who slow down. Between Florence and Siena, the Chianti hills roll past medieval hill towns, olive groves and family estates that have been making wine for six hundred years.\n\nWe stay in restored farmhouses rather than chain hotels, cook with local nonnas, and time the famous sights for the quiet hours.",
            'best_time' => 'May, June, September and October. Harvest season (late September) is magical.',
            'image' => 'tuscan-countryside.jpg', 'image_credit' => 'Photo: Sandro Mattei, CC0, via Wikimedia Commons',
            'is_featured' => true,
        ],
        [
            'slug' => 'lake-bled', 'name' => 'Lake Bled & Julian Alps', 'country' => 'Slovenia', 'region' => 'europe',
            'tagline' => 'An island church, an emerald lake and Alpine trails to yourself.',
            'description' => "Slovenia packs Alpine peaks, turquoise rivers and storybook lakes into a country you can cross in three hours. Lake Bled, with its island church and clifftop castle, is the gateway to Triglav National Park.\n\nOur walking routes link mountain huts and valley villages, with luggage moved ahead each day.",
            'best_time' => 'June to September for high trails; May and October for quiet valleys.',
            'image' => 'lake-bled.jpg', 'image_credit' => 'Photo: Tom Salmon, CC BY 2.0, via Wikimedia Commons',
            'is_featured' => false,
        ],
        [
            'slug' => 'iceland', 'name' => 'Iceland', 'country' => 'Iceland', 'region' => 'europe',
            'tagline' => 'Fire, ice and the northern lights.',
            'description' => "Iceland is a young island still being built: lava fields steam beside glaciers, waterfalls pour off black cliffs and, in winter, the aurora dances overhead.\n\nTravelling in a small group with an Icelandic guide means we can wait out the weather, take the gravel detours and find hot springs without a tour bus in sight.",
            'best_time' => 'June to August for the midnight sun; late September to March for the northern lights.',
            'image' => 'icelandic-volcano.jpg', 'image_credit' => 'Photo: Giles Laurent, CC BY-SA 4.0, via Wikimedia Commons',
            'is_featured' => true,
        ],
        [
            'slug' => 'cape-town', 'name' => 'Cape Town & the Cape', 'country' => 'South Africa', 'region' => 'africa',
            'tagline' => 'Table Mountain, winelands and the Big Five.',
            'description' => "Few cities have a setting like Cape Town's: a flat-topped mountain, two oceans and a coastline of penguins and whales. An hour inland, the Cape Winelands produce some of the world's best-value wines.\n\nWe pair the Cape with a private game reserve, so you leave with lions and leopards as well as sunsets.",
            'best_time' => 'October to April for beach weather; June to November for whale watching.',
            'image' => 'cape-town-lions-head.jpg', 'image_credit' => 'Photo: Marcelo Novais, CC0, via Wikimedia Commons',
            'is_featured' => false,
        ],
        [
            'slug' => 'great-barrier-reef', 'name' => 'Great Barrier Reef', 'country' => 'Australia', 'region' => 'asia-pacific',
            'tagline' => 'The world\'s largest reef, beside the oldest rainforest.',
            'description' => "Tropical North Queensland is where two World Heritage sites meet: the Great Barrier Reef and the Daintree Rainforest, older than the Amazon.\n\nWe snorkel the outer reef with marine biologists, walk the rainforest with Kuku Yalanji guides and stay in eco-lodges that put money back into conservation.",
            'best_time' => 'June to October: dry, sunny and stinger-free.',
            'image' => 'great-barrier-reef.jpg', 'image_credit' => 'Photo: Ank Kumar, CC BY-SA 4.0, via Wikimedia Commons',
            'is_featured' => false,
        ],
        [
            'slug' => 'maldives', 'name' => 'Maldives', 'country' => 'Maldives', 'region' => 'asia-pacific',
            'tagline' => 'Coral atolls, overwater villas and manta rays.',
            'description' => "Twenty-six atolls of white sand scattered across the Indian Ocean, with house reefs a few fin-kicks from your door.\n\nOur escape combines a local island guesthouse, where you'll meet Maldivians and eat their food, with a resort stay for the overwater-villa moment.",
            'best_time' => 'November to April: calm seas and blue skies.',
            'image' => 'maldives-atolls.jpg', 'image_credit' => 'Photo: Nattu Adnan, CC0, via Wikimedia Commons',
            'is_featured' => false,
        ],
        [
            'slug' => 'alaska', 'name' => 'Alaska', 'country' => 'United States', 'region' => 'americas',
            'tagline' => 'Glaciers, grizzlies and the last great wilderness.',
            'description' => "Alaska is bigger than Texas, California and Montana combined, and most of it has no roads. Glaciers calve into fjords, bears fish for salmon and Denali rises over everything.\n\nWe travel by rail, small ship and bush plane, staying in wilderness lodges where the wildlife comes to you.",
            'best_time' => 'Mid-May to mid-September. July and August for bears on the salmon runs.',
            'image' => 'wrangell-glacier.jpg', 'image_credit' => 'Photo: U.S. Fish and Wildlife Service, public domain, via Wikimedia Commons',
            'is_featured' => false,
        ],
    ];

    /**
     * @var list<array<string, mixed>>
     */
    private const array TOURS = [
        [
            'slug' => 'santorini-naxos-island-hopper', 'destination' => 'santorini', 'name' => 'Santorini & Naxos Island Hopper',
            'style' => 'beach', 'duration_days' => 8, 'group_size_max' => 12, 'price' => 289000, 'supplement' => 69000, 'is_featured' => true,
            'summary' => 'Caldera sunsets, volcanic wines and the quiet beaches of Naxos, by fast ferry.',
            'description' => "Start on Santorini's caldera rim, walking clifftop paths, exploring Bronze Age Akrotiri and tasting wines grown in volcanic ash. Then ferry to Naxos, the Cyclades' best-kept secret, for marble villages, long sandy beaches and tavernas where the owner still fishes.\n\nSmall boutique hotels throughout, with sea views on Santorini.",
            'highlights' => ['Sunset sail around the caldera with dinner on board', 'Private tasting at a family winery in Pyrgos', 'Rim walk from Fira to Oia with your guide', 'Two free days on Naxos\'s Plaka beach'],
            'itinerary' => [
                ['day' => '1', 'title' => 'Arrive on Santorini', 'body' => 'Airport pick-up and transfer to your caldera-view hotel in Firostefani. Welcome dinner overlooking the volcano.'],
                ['day' => '2', 'title' => 'Akrotiri and the south', 'body' => 'Explore the Minoan city buried by the eruption, then the Red Beach and a winery lunch in Megalochori.'],
                ['day' => '3', 'title' => 'The rim walk to Oia', 'body' => 'A 10 km morning walk along the caldera edge. Afternoon free; sunset sail with dinner on board.'],
                ['day' => '4', 'title' => 'Ferry to Naxos', 'body' => 'Fast ferry to Naxos. Afternoon walk through the Venetian kastro and the old town lanes.'],
                ['day' => '5–6', 'title' => 'Mountain villages and beaches', 'body' => 'A day in the marble villages of Apeiranthos and Halki with a kitron distillery visit, then a free beach day.'],
                ['day' => '7', 'title' => 'Cooking on a family farm', 'body' => 'Cook a long lunch with a Naxian family, then a farewell dinner in the port.'],
                ['day' => '8', 'title' => 'Depart', 'body' => 'Transfer to Naxos airport or the ferry for Athens.'],
            ],
            'inclusions' => ['7 nights in boutique hotels', 'Daily breakfast, 3 lunches, 3 dinners', 'Expert local guide throughout', 'Ferry between islands', 'Sunset sail and winery tasting', 'Airport and port transfers'],
        ],
        [
            'slug' => 'tuscany-slow-food-wine', 'destination' => 'tuscany', 'name' => 'Tuscany Slow Food & Wine',
            'style' => 'food-and-wine', 'duration_days' => 7, 'group_size_max' => 12, 'price' => 325000, 'supplement' => 75000, 'is_featured' => true,
            'summary' => 'A week in a Chianti farmhouse: cooking classes, cellar visits and Renaissance Florence.',
            'description' => "Base yourself in a restored 15th-century farmhouse in the Chianti hills and let Tuscany come to you. Cook with a local nonna, hunt for truffles with a farmer and his dog, and taste Brunello in the cellars where it ages.\n\nDay trips take you to Siena's Campo, the towers of San Gimignano and the Uffizi in Florence, timed to avoid the crowds.",
            'highlights' => ['Truffle hunt and lunch on a family farm', 'Hands-on pasta class with a Tuscan nonna', 'Private Brunello tasting in Montalcino', 'Early-entry guided visit to the Uffizi'],
            'itinerary' => [
                ['day' => '1', 'title' => 'Benvenuti in Chianti', 'body' => 'Meet in Florence and transfer to the farmhouse. Aperitivo on the terrace and welcome dinner.'],
                ['day' => '2', 'title' => 'Siena and the Crete Senesi', 'body' => 'Morning in Siena\'s medieval centre, afternoon drive through the clay hills to an organic olive oil mill.'],
                ['day' => '3', 'title' => 'Truffles and pasta', 'body' => 'Morning truffle hunt with lunch on the farm, then a pasta-making class back at the house.'],
                ['day' => '4', 'title' => 'Montalcino and Val d\'Orcia', 'body' => 'Brunello tasting, the cypress roads of the Val d\'Orcia and a stop in Pienza for pecorino.'],
                ['day' => '5', 'title' => 'San Gimignano', 'body' => 'Towers, gelato and Vernaccia. Free afternoon by the pool.'],
                ['day' => '6', 'title' => 'Florence', 'body' => 'Early-entry Uffizi tour, then a free afternoon. Farewell dinner in a Florentine trattoria.'],
                ['day' => '7', 'title' => 'Arrivederci', 'body' => 'Transfer to Florence station or airport.'],
            ],
            'inclusions' => ['6 nights in a restored farmhouse', 'Daily breakfast, 4 lunches, 5 dinners with wine', 'Cooking class and truffle hunt', '3 winery and producer visits', 'Expert local guide throughout', 'All transport in Tuscany'],
        ],
        [
            'slug' => 'florence-chianti-long-weekend', 'destination' => 'tuscany', 'name' => 'Florence & Chianti Long Weekend',
            'style' => 'culture', 'duration_days' => 4, 'group_size_max' => 14, 'price' => 139000, 'supplement' => 32000, 'is_featured' => false,
            'summary' => 'Renaissance masterpieces and a day in the vineyards, in four unhurried days.',
            'description' => "The perfect add-on to a European trip, or a long weekend on its own. Two days with an art historian in Florence, from Michelangelo's David to Brunelleschi's dome, then a day in the Chianti hills with lunch at a family estate.",
            'highlights' => ['Skip-the-line Accademia and Uffizi', 'Climb Brunelleschi\'s dome', 'Vineyard lunch in Greve in Chianti'],
            'itinerary' => [
                ['day' => '1', 'title' => 'Florence at dusk', 'body' => 'Check in near the Duomo and join an evening walk through the old town.'],
                ['day' => '2', 'title' => 'The Renaissance', 'body' => 'Accademia, Duomo and the Uffizi with an art historian.'],
                ['day' => '3', 'title' => 'Chianti', 'body' => 'A day in the vineyards with lunch and tasting at a family estate.'],
                ['day' => '4', 'title' => 'Depart', 'body' => 'Free morning in Florence before you head off.'],
            ],
            'inclusions' => ['3 nights in a central Florence hotel', 'Daily breakfast and 1 vineyard lunch', 'Museum entries and art historian guide', 'Chianti day trip'],
        ],
        [
            'slug' => 'lake-bled-julian-alps-hiking', 'destination' => 'lake-bled', 'name' => 'Lake Bled & Julian Alps Hiking',
            'style' => 'adventure', 'duration_days' => 7, 'group_size_max' => 12, 'price' => 219000, 'supplement' => 48000, 'is_featured' => false,
            'summary' => 'Hut-to-valley hiking through Triglav National Park, with your bags moved ahead.',
            'description' => "Walk the Julian Alps from Lake Bled to the emerald Soča valley, through flower meadows, gorges and passes with views to Triglav. Days are 12–18 km with an experienced mountain guide, and your luggage travels ahead by van.\n\nNights are in family-run guesthouses with hearty Slovenian cooking.",
            'highlights' => ['Row a traditional pletna boat to Bled island', 'Vintgar Gorge boardwalk', 'Cross the Vršič Pass', 'Swim in the Soča river'],
            'itinerary' => [
                ['day' => '1', 'title' => 'Lake Bled', 'body' => 'Transfer from Ljubljana. Evening walk around the lake and the famous cream cake.'],
                ['day' => '2', 'title' => 'Vintgar Gorge and Bled island', 'body' => 'Morning gorge hike, afternoon pletna boat to the island church.'],
                ['day' => '3–4', 'title' => 'Into Triglav National Park', 'body' => 'Hike to Lake Bohinj and up to the Seven Lakes valley, staying in a mountain hut.'],
                ['day' => '5', 'title' => 'Vršič Pass', 'body' => 'Cross the highest road pass in Slovenia on foot, descending into the Trenta valley.'],
                ['day' => '6', 'title' => 'The Soča valley', 'body' => 'Riverside trail to Kobarid, with an optional rafting afternoon.'],
                ['day' => '7', 'title' => 'Depart', 'body' => 'Transfer back to Ljubljana.'],
            ],
            'inclusions' => ['6 nights in guesthouses and a mountain hut', 'Daily breakfast, 5 dinners', 'Certified mountain guide', 'Luggage transfers every day', 'Pletna boat and gorge entry'],
        ],
        [
            'slug' => 'iceland-ring-road-adventure', 'destination' => 'iceland', 'name' => 'Iceland Ring Road Adventure',
            'style' => 'adventure', 'duration_days' => 10, 'group_size_max' => 12, 'price' => 445000, 'supplement' => 99000, 'is_featured' => true,
            'summary' => 'The full circle: glaciers, black-sand beaches, whale watching and hot springs.',
            'description' => "Circle the whole island on Route 1 in a small 4x4 coach with an Icelandic guide. Hike on a glacier, walk behind waterfalls, watch icebergs drift through Jökulsárlón lagoon, look for whales in Húsavík and soak in geothermal pools far from the crowds.\n\nCountry hotels and farm stays throughout, with time built in for the weather to do its thing.",
            'highlights' => ['Guided glacier hike on Sólheimajökull', 'Boat among the icebergs at Jökulsárlón', 'Whale watching from Húsavík', 'Mývatn Nature Baths'],
            'itinerary' => [
                ['day' => '1', 'title' => 'Reykjavík', 'body' => 'Arrive and meet the group for a welcome dinner in the old harbour.'],
                ['day' => '2', 'title' => 'The Golden Circle', 'body' => 'Þingvellir rift valley, Geysir and Gullfoss, ending at a farm stay.'],
                ['day' => '3–4', 'title' => 'The south coast', 'body' => 'Seljalandsfoss, Skógafoss, a glacier hike and the black sands of Reynisfjara.'],
                ['day' => '5', 'title' => 'Glacier lagoon', 'body' => 'Boat trip at Jökulsárlón and Diamond Beach, then the East Fjords.'],
                ['day' => '6–7', 'title' => 'Mývatn and the north', 'body' => 'Lava fields, Dettifoss and an evening soak at the Nature Baths. Whale watching from Húsavík.'],
                ['day' => '8', 'title' => 'Akureyri and Snæfellsnes', 'body' => 'The northern capital, then west to Kirkjufell mountain.'],
                ['day' => '9', 'title' => 'Back to Reykjavík', 'body' => 'Snæfellsjökull National Park on the way. Farewell dinner.'],
                ['day' => '10', 'title' => 'Depart', 'body' => 'Transfer to Keflavík airport.'],
            ],
            'inclusions' => ['9 nights in country hotels and farm stays', 'Daily breakfast, 6 dinners', 'Icelandic guide and 4x4 coach', 'Glacier hike with equipment', 'Lagoon boat and whale watching', 'Mývatn Nature Baths entry'],
        ],
        [
            'slug' => 'iceland-northern-lights-break', 'destination' => 'iceland', 'name' => 'Northern Lights Winter Break',
            'style' => 'wildlife', 'duration_days' => 5, 'group_size_max' => 14, 'price' => 198000, 'supplement' => 45000, 'is_featured' => false,
            'summary' => 'Aurora hunting from a countryside hotel, with ice caves and hot springs by day.',
            'description' => 'Four nights at a rural hotel far from city lights, with an aurora wake-up call and nightly hunts with an astrophotographer. Days bring an ice cave inside Vatnajökull, the Golden Circle under snow and a long soak in a geothermal lagoon.',
            'highlights' => ['Nightly aurora hunts with a photographer', 'Crystal ice cave tour', 'Secret Lagoon geothermal pool'],
            'itinerary' => [
                ['day' => '1', 'title' => 'Into the countryside', 'body' => 'Transfer to the hotel. First aurora hunt after dinner.'],
                ['day' => '2', 'title' => 'Golden Circle in winter', 'body' => 'Þingvellir, Geysir and a frozen Gullfoss, then the Secret Lagoon.'],
                ['day' => '3', 'title' => 'Ice cave', 'body' => 'Super-jeep to the glacier and a guided walk inside a blue ice cave.'],
                ['day' => '4', 'title' => 'South coast', 'body' => 'Waterfalls and black sand beaches. Farewell dinner and a last hunt.'],
                ['day' => '5', 'title' => 'Depart', 'body' => 'Transfer to Keflavík airport.'],
            ],
            'inclusions' => ['4 nights in a countryside hotel', 'Daily breakfast and dinner', 'Nightly aurora hunts', 'Ice cave tour with equipment', 'Lagoon entry'],
        ],
        [
            'slug' => 'cape-town-winelands-safari', 'destination' => 'cape-town', 'name' => 'Cape Town, Winelands & Safari',
            'style' => 'wildlife', 'duration_days' => 11, 'group_size_max' => 12, 'price' => 489000, 'supplement' => 95000, 'is_featured' => false,
            'summary' => 'Table Mountain, penguins and Stellenbosch, then three nights on a Big Five reserve.',
            'description' => 'Begin in Cape Town with Table Mountain, the Cape Peninsula and Boulders Beach penguins. Move to Stellenbosch and Franschhoek for wine estates and Cape Malay cooking, then fly to a private game reserve for twice-daily safari drives with expert trackers.',
            'highlights' => ['Cable car up Table Mountain', 'Penguins at Boulders Beach', 'Wine-tram day in Franschhoek', 'Six game drives on a private Big Five reserve'],
            'itinerary' => [
                ['day' => '1', 'title' => 'Cape Town', 'body' => 'Arrive and settle in at the V&A Waterfront. Welcome dinner.'],
                ['day' => '2–3', 'title' => 'Mountain and peninsula', 'body' => 'Table Mountain, Bo-Kaap, then the Cape of Good Hope and Boulders Beach penguins.'],
                ['day' => '4–6', 'title' => 'The Winelands', 'body' => 'Stellenbosch and Franschhoek estates, a Cape Malay cooking class and the wine tram.'],
                ['day' => '7', 'title' => 'Fly to the bush', 'body' => 'Short flight to the reserve. Afternoon game drive and sundowners.'],
                ['day' => '8–10', 'title' => 'On safari', 'body' => 'Morning and evening drives looking for lion, leopard, elephant, buffalo and rhino, plus a guided bush walk.'],
                ['day' => '11', 'title' => 'Depart', 'body' => 'Fly to Johannesburg for your onward connection.'],
            ],
            'inclusions' => ['10 nights in hotels and a safari lodge', 'All meals on safari; breakfast elsewhere', 'Internal flight to the reserve', '6 game drives with tracker', 'Table Mountain cable car and peninsula tour', 'Expert local guides'],
        ],
        [
            'slug' => 'reef-and-rainforest-queensland', 'destination' => 'great-barrier-reef', 'name' => 'Reef & Rainforest Queensland',
            'style' => 'wildlife', 'duration_days' => 9, 'group_size_max' => 12, 'price' => 369000, 'supplement' => 82000, 'is_featured' => false,
            'summary' => 'Snorkel the outer reef with marine biologists and walk the ancient Daintree.',
            'description' => "Two of the world's great ecosystems in one trip. Spend two days on the outer Great Barrier Reef with marine biologists, then head north to the Daintree, where the rainforest meets the reef at Cape Tribulation.\n\nAll lodges are eco-certified, and part of every booking funds reef restoration.",
            'highlights' => ['Two outer-reef snorkel days', 'Dreamtime walk with Kuku Yalanji guides', 'Night walk in the Daintree', 'Kuranda scenic railway'],
            'itinerary' => [
                ['day' => '1', 'title' => 'Cairns', 'body' => 'Arrive and meet your group on the Esplanade.'],
                ['day' => '2–3', 'title' => 'The outer reef', 'body' => 'Two days snorkelling the outer reef with marine biologists. Optional intro dive.'],
                ['day' => '4', 'title' => 'Kuranda', 'body' => 'Scenic railway up to the rainforest village, Skyrail back down.'],
                ['day' => '5–7', 'title' => 'The Daintree', 'body' => 'Mossman Gorge Dreamtime walk, a river cruise for crocodiles and a night walk.'],
                ['day' => '8', 'title' => 'Port Douglas', 'body' => 'Free day on Four Mile Beach. Farewell dinner.'],
                ['day' => '9', 'title' => 'Depart', 'body' => 'Transfer to Cairns airport.'],
            ],
            'inclusions' => ['8 nights in eco-lodges and hotels', 'Daily breakfast, 4 lunches, 4 dinners', '2 outer-reef trips with gear', 'Indigenous-guided Dreamtime walk', 'Kuranda railway and Skyrail'],
        ],
        [
            'slug' => 'maldives-island-escape', 'destination' => 'maldives', 'name' => 'Maldives Island Escape',
            'style' => 'beach', 'duration_days' => 7, 'group_size_max' => 10, 'price' => 395000, 'supplement' => 140000, 'is_featured' => false,
            'summary' => 'Local island life, manta rays and three nights in an overwater villa.',
            'description' => "Start on a local island, where you'll snorkel with turtles, fish at sunset with a Maldivian crew and eat at a family table. Then take a seaplane to a resort for three nights in an overwater villa with your own steps into the lagoon.",
            'highlights' => ['Snorkel with manta rays and turtles', 'Sunset fishing with a local crew', 'Seaplane transfer', '3 nights in an overwater villa'],
            'itinerary' => [
                ['day' => '1', 'title' => 'Malé to Maafushi', 'body' => 'Speedboat to a local island guesthouse.'],
                ['day' => '2–3', 'title' => 'Island life', 'body' => 'Sandbank picnic, turtle snorkelling and sunset fishing.'],
                ['day' => '4', 'title' => 'Seaplane', 'body' => 'Fly over the atolls to your resort.'],
                ['day' => '5–6', 'title' => 'Overwater days', 'body' => 'Manta snorkel trip, spa and the lagoon at your door.'],
                ['day' => '7', 'title' => 'Depart', 'body' => 'Seaplane back to Malé.'],
            ],
            'inclusions' => ['3 nights local guesthouse, 3 nights overwater villa', 'Half board on the local island; full board at the resort', 'Seaplane and speedboat transfers', 'Manta and turtle snorkel trips'],
        ],
        [
            'slug' => 'alaska-glaciers-wildlife', 'destination' => 'alaska', 'name' => 'Alaska Glaciers & Wildlife',
            'style' => 'adventure', 'duration_days' => 12, 'group_size_max' => 12, 'price' => 529000, 'supplement' => 115000, 'is_featured' => false,
            'summary' => 'Denali, Kenai Fjords and a bush-plane flight to watch bears fishing for salmon.',
            'description' => "From Anchorage, ride the Alaska Railroad to Denali for wildlife and the continent's highest peak. Cruise Kenai Fjords among calving glaciers and humpbacks, then fly by bush plane to a lodge where brown bears fish the salmon runs.",
            'highlights' => ['Alaska Railroad to Denali', 'Kenai Fjords glacier and whale cruise', 'Bush-plane bear viewing', 'Wilderness lodge in Wrangell–St. Elias'],
            'itinerary' => [
                ['day' => '1', 'title' => 'Anchorage', 'body' => 'Arrive and meet the group.'],
                ['day' => '2–4', 'title' => 'Denali', 'body' => 'Rail north to Denali. Park bus into the wilderness and a ranger-led hike.'],
                ['day' => '5–6', 'title' => 'Kenai Peninsula', 'body' => 'Seward and a full-day Kenai Fjords cruise.'],
                ['day' => '7–8', 'title' => 'Bears', 'body' => 'Bush-plane flight to a bear-viewing lodge on the salmon runs.'],
                ['day' => '9–11', 'title' => 'Wrangell–St. Elias', 'body' => 'Glacier hiking and the ghost town of Kennecott from a wilderness lodge.'],
                ['day' => '12', 'title' => 'Depart', 'body' => 'Fly back to Anchorage for your onward flight.'],
            ],
            'inclusions' => ['11 nights in lodges and hotels', 'Daily breakfast, 8 lunches, 8 dinners', 'Alaska Railroad and bush-plane flights', 'Kenai Fjords cruise', 'Glacier hike with gear', 'Expert naturalist guide'],
        ],
    ];

    /**
     * Sample booking requests: tour slug (null for tailor-made), destination,
     * departure position, party, status, days ago, and who sent it.
     *
     * @var list<array<string, mixed>>
     */
    private const array INQUIRIES = [
        ['tour' => 'iceland-ring-road-adventure', 'departure' => 0, 'adults' => 2, 'children' => 0, 'status' => 'new', 'days_ago' => 0, 'name' => 'Olivia Tremblay', 'email' => 'olivia.tremblay@example.com', 'phone' => '+1 (514) 555-0187', 'message' => 'Celebrating our 10th anniversary. Any chance of a room with a view of the northern lights?'],
        ['tour' => 'tuscany-slow-food-wine', 'departure' => 1, 'adults' => 4, 'children' => 0, 'status' => 'new', 'days_ago' => 1, 'name' => 'Arjun Patel', 'email' => 'arjun.patel@example.com', 'phone' => null, 'message' => 'Two couples travelling together. One of us is vegetarian.'],
        ['tour' => null, 'destination' => 'maldives', 'month' => '+4 months', 'adults' => 2, 'children' => 0, 'status' => 'new', 'days_ago' => 1, 'name' => 'Grace Campbell', 'email' => 'grace.campbell@example.com', 'phone' => '+1 (604) 555-0123', 'message' => "Honeymoon! We'd love 10 days, mostly relaxing, with one or two diving days. Budget around \$6,000 each."],
        ['tour' => 'santorini-naxos-island-hopper', 'departure' => 0, 'adults' => 1, 'children' => 0, 'status' => 'contacted', 'days_ago' => 3, 'name' => 'Test User', 'email' => 'test@example.com', 'phone' => null, 'message' => 'Travelling solo. Is the single supplement negotiable if I share?'],
        ['tour' => 'cape-town-winelands-safari', 'departure' => 1, 'adults' => 2, 'children' => 2, 'status' => 'contacted', 'days_ago' => 4, 'name' => 'Liam Nguyen', 'email' => 'liam.nguyen@example.com', 'phone' => '+1 (416) 555-0199', 'message' => 'Our kids are 9 and 11. Are they old enough for the game drives?'],
        ['tour' => 'lake-bled-julian-alps-hiking', 'departure' => 0, 'adults' => 2, 'children' => 0, 'status' => 'confirmed', 'days_ago' => 9, 'name' => 'Hannah Walker', 'email' => 'hannah.walker@example.com', 'phone' => null, 'message' => null],
        ['tour' => 'tuscany-slow-food-wine', 'departure' => 0, 'adults' => 2, 'children' => 0, 'status' => 'confirmed', 'days_ago' => 12, 'name' => 'Test User', 'email' => 'test@example.com', 'phone' => null, 'message' => 'Could you add two extra nights in Florence at the end?'],
        ['tour' => 'alaska-glaciers-wildlife', 'departure' => 0, 'adults' => 3, 'children' => 0, 'status' => 'confirmed', 'days_ago' => 15, 'name' => 'Mateo Roy', 'email' => 'mateo.roy@example.com', 'phone' => '+1 (403) 555-0164', 'message' => null],
        ['tour' => 'reef-and-rainforest-queensland', 'departure' => 1, 'adults' => 2, 'children' => 0, 'status' => 'declined', 'days_ago' => 20, 'name' => 'Aisha Singh', 'email' => 'aisha.singh@example.com', 'phone' => null, 'message' => 'Dates no longer work for us, sorry!'],
        ['tour' => null, 'destination' => 'tuscany', 'month' => '+6 months', 'adults' => 6, 'children' => 2, 'status' => 'contacted', 'days_ago' => 6, 'name' => 'Samuel MacDonald', 'email' => 'samuel.macdonald@example.com', 'phone' => '+1 (902) 555-0110', 'message' => "Family reunion for Mum's 70th. We'd like a private villa with a cook for a week, plus a day in Florence."],
    ];

    /**
     * How full each of a tour's departures already is, as a share of its
     * seats, so the dates table shows a believable mix.
     *
     * @var list<float>
     */
    private const array FILL = [0.6, 0.25, 0.85, 0.1];

    public function run(): void
    {
        $this->seedSettings();
        $this->seedDestinations();
        $this->seedTours();
        $this->seedDepartures();

        if (app(DevLoginAccounts::class)->enabled()) {
            $this->seedInquiries();
        }
    }

    private function seedSettings(): void
    {
        foreach (self::SETTINGS as $rawKey => $value) {
            $key = SettingKey::from($rawKey);

            Setting::query()->firstOrCreate(['key' => $key->value], ['value' => $key->cast($value)]);
        }
    }

    private function seedDestinations(): void
    {
        foreach (self::DESTINATIONS as $position => $destination) {
            $image = $destination['image'];
            unset($destination['image']);

            Destination::query()->firstOrCreate(['slug' => $destination['slug']], [
                ...$destination,
                'image_path' => $this->publishImage($image),
                'sort_order' => $position + 1,
            ]);
        }
    }

    private function seedTours(): void
    {
        $destinations = Destination::query()->pluck('id', 'slug');

        foreach (self::TOURS as $position => $tour) {
            Tour::query()->firstOrCreate(['slug' => $tour['slug']], [
                'destination_id' => $destinations[$tour['destination']],
                'name' => $tour['name'],
                'style' => $tour['style'],
                'summary' => $tour['summary'],
                'description' => $tour['description'],
                'duration_days' => $tour['duration_days'],
                'group_size_max' => $tour['group_size_max'],
                'price_per_person_cents' => $tour['price'],
                'single_supplement_cents' => $tour['supplement'],
                'highlights' => $tour['highlights'],
                'itinerary' => $tour['itinerary'],
                'inclusions' => $tour['inclusions'],
                'is_featured' => $tour['is_featured'],
                'is_published' => true,
                'sort_order' => $position + 1,
            ]);
        }
    }

    /**
     * Four departures per tour, five to seven weeks apart, starting a few
     * weeks out. The third of the first tour is sold out and one departure
     * per tour carries an early-bird price, so every state shows somewhere.
     */
    private function seedDepartures(): void
    {
        if (TourDeparture::query()->exists()) {
            return;
        }

        foreach (Tour::query()->orderBy('sort_order')->get() as $index => $tour) {
            $startsOn = now()->startOfWeek()->addWeeks(3 + $index % 3)->addDays($index % 2 === 0 ? 5 : 0);

            foreach (self::FILL as $position => $fill) {
                $seatsHeld = (int) floor($tour->group_size_max * $fill);

                if ($index === 0 && $position === 2) {
                    $seatsHeld = $tour->group_size_max;
                }

                TourDeparture::query()->create([
                    'tour_id' => $tour->id,
                    'starts_on' => $startsOn->toDateString(),
                    'price_per_person_cents' => $position === 3 ? (int) round($tour->price_per_person_cents * 0.9 / 1000) * 1000 : null,
                    'seats_total' => $tour->group_size_max,
                    'seats_held' => $seatsHeld,
                ]);

                $startsOn = $startsOn->addWeeks(5 + ($index + $position) % 3);
            }
        }
    }

    private function seedInquiries(): void
    {
        if (TripInquiry::query()->exists()) {
            return;
        }

        $tours = Tour::query()->with('departures.tour')->get()->keyBy('slug');
        $destinations = Destination::query()->pluck('id', 'slug');

        foreach (self::INQUIRIES as $sample) {
            $tour = $sample['tour'] !== null ? $tours[$sample['tour']] : null;
            $departure = $tour?->departures->values()->get($sample['departure'] ?? 0);
            $travellers = $sample['adults'] + $sample['children'];
            $status = InquiryStatus::from($sample['status']);

            $inquiry = new TripInquiry([
                'tour_id' => $tour?->id,
                'tour_departure_id' => $departure?->id,
                'destination_id' => $tour !== null ? $tour->destination_id : $destinations[$sample['destination'] ?? ''] ?? null,
                'name' => $sample['name'],
                'email' => $sample['email'],
                'phone' => $sample['phone'],
                'adults' => $sample['adults'],
                'children' => $sample['children'],
                'travel_month' => isset($sample['month']) ? now()->modify($sample['month'])->format('F Y') : null,
                'quoted_total_cents' => $tour !== null
                    ? TripQuote::for($tour, $departure, $sample['adults'], $sample['children'])->totalCents()
                    : null,
                'message' => $sample['message'],
            ]);
            // Set here rather than left to the model's creating hook, which
            // DatabaseSeeder's WithoutModelEvents switches off.
            $inquiry->reference = TripInquiry::newReference();
            $inquiry->status = $status;
            $inquiry->created_at = now()->subDays($sample['days_ago'])->subHours(3);
            $inquiry->updated_at = $inquiry->created_at;

            if ($status === InquiryStatus::Confirmed) {
                $inquiry->confirmed_at = $inquiry->created_at->addDay();

                if ($departure !== null && $departure->seatsLeft() >= $travellers) {
                    $departure->increment('seats_held', $travellers);
                }
            }

            $inquiry->save();
        }
    }

    /**
     * Copy a bundled photo onto the public disk and return its path there.
     */
    private function publishImage(string $file): string
    {
        $path = 'travel/'.$file;
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            $disk->put($path, (string) file_get_contents(__DIR__.'/images/travel/'.$file));
        }

        return $path;
    }
}
