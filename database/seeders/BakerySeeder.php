<?php

namespace Database\Seeders;

use App\Bakery\Fulfilment;
use App\Bakery\InquiryStatus;
use App\Bakery\Occasion;
use App\Models\MenuItem;
use App\Models\OrderInquiry;
use App\Models\Page;
use App\Models\Setting;
use App\Settings\SettingKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * The demo home bakery: its brand, its menu, its About and Contact copy, and a
 * handful of sample order inquiries so the admin panel has something to show.
 *
 * Runs on every container boot (docker/entrypoint/entrypoint.sh), so it must be
 * idempotent and must not use factories -- faker is absent from the --no-dev
 * production image. See .ai/rules/seeders.md. Everything is firstOrCreate, so
 * the owner's own edits to prices, copy and settings survive a redeploy, and
 * the sample inquiries are only written into an empty table.
 *
 * Runs BEFORE SettingsSeeder and PagesSeeder, so the bakery's details land
 * ahead of their generic placeholders.
 *
 * The menu photos ship beside this seeder in images/bakery/menu and are copied
 * onto the public disk, where uploads made in the admin panel also live. Each
 * is credited in its item's image_credit, as the Creative Commons licences
 * require.
 */
class BakerySeeder extends Seeder
{
    /**
     * The bakery's brand and contact details, keyed by SettingKey value.
     *
     * @var array<string, string>
     */
    private const array SETTINGS = [
        'business_name' => 'Hearth & Honey Bakery',
        'business_address' => "Pickup from our home kitchen in Maple Grove\nFull address sent with your order confirmation",
        'business_phone' => '(555) 014-2290',
        'business_email' => 'hello@hearthandhoney.example',
        'seo_title' => 'Hearth & Honey Bakery — Home-baked bread, cakes & pastries',
        'seo_description' => 'A small home bakery making sourdough, laminated pastries and celebration cakes to order. Browse the menu and request pickup or local delivery.',
        'timezone' => 'America/Toronto',
    ];

    /**
     * The demo menu, in menu order.
     *
     * @var list<array<string, mixed>>
     */
    private const array MENU = [
        [
            'slug' => 'country-sourdough', 'name' => 'Country Sourdough Loaf', 'category' => 'breads',
            'description' => 'Our everyday loaf: wild yeast, a 48-hour ferment and a deep, crackly crust. Keeps beautifully for three days.',
            'price_cents' => 900, 'price_unit' => 'per loaf', 'serves' => null, 'dietary' => ['vegan', 'dairy-free'], 'notice_days' => 3,
            'is_featured' => false, 'is_seasonal' => false,
            'image_credit' => 'Photo: Agelaia, CC BY 4.0, via Wikimedia Commons',
        ],
        [
            'slug' => 'rosemary-focaccia', 'name' => 'Rosemary Sea Salt Focaccia', 'category' => 'breads',
            'description' => 'Pillowy, olive-oil rich and dimpled with fresh rosemary and flaky salt. Made for sharing boards.',
            'price_cents' => 1400, 'price_unit' => 'half tray', 'serves' => '8', 'dietary' => ['vegan', 'dairy-free'], 'notice_days' => 2,
            'is_featured' => false, 'is_seasonal' => false,
            'image_credit' => 'Photo: jeffreyw, CC BY 2.0, via Wikimedia Commons',
        ],
        [
            'slug' => 'butter-croissants', 'name' => 'All-Butter Croissants', 'category' => 'pastries',
            'description' => 'Hand-laminated over three days with cultured butter. Shatteringly crisp outside, honeycomb inside.',
            'price_cents' => 2100, 'price_unit' => 'half dozen', 'serves' => null, 'dietary' => [], 'notice_days' => 3,
            'is_featured' => false, 'is_seasonal' => false,
            'image_credit' => 'Photo: Rama, CC BY-SA 2.0 FR, via Wikimedia Commons',
        ],
        [
            'slug' => 'cinnamon-rolls', 'name' => 'Cinnamon Rolls', 'category' => 'pastries',
            'description' => 'Soft brioche swirled with brown sugar and Ceylon cinnamon, finished with cream cheese icing.',
            'price_cents' => 2400, 'price_unit' => 'half dozen', 'serves' => null, 'dietary' => [], 'notice_days' => 2,
            'is_featured' => true, 'is_seasonal' => false,
            'image_credit' => 'Photo: Pannet, CC BY 4.0, via Wikimedia Commons',
        ],
        [
            'slug' => 'celebration-layer-cake', 'name' => 'Sprinkle Celebration Cake', 'category' => 'cakes',
            'description' => 'Three layers of vanilla sponge, sprinkles inside and out, with your message piped on top. Colours matched to your party.',
            'price_cents' => 6500, 'price_unit' => '6-inch, 3 layers', 'serves' => '10–12', 'dietary' => [], 'notice_days' => 7,
            'is_featured' => true, 'is_seasonal' => false,
            'image_credit' => 'Photo: Annie Spratt, CC0, via Wikimedia Commons',
        ],
        [
            'slug' => 'chocolate-fudge-cake', 'name' => 'Chocolate Fudge Cake', 'category' => 'cakes',
            'description' => 'Dark, moist chocolate sponge with silky fudge frosting and a cloud of whipped cream.',
            'price_cents' => 5200, 'price_unit' => '8-inch cake', 'serves' => '12', 'dietary' => [], 'notice_days' => 4,
            'is_featured' => false, 'is_seasonal' => false,
            'image_credit' => 'Photo: Andy Li, CC0, via Wikimedia Commons',
        ],
        [
            'slug' => 'carrot-cake', 'name' => 'Carrot & Walnut Cake', 'category' => 'cakes',
            'description' => 'Spiced carrot sponge packed with toasted walnuts, under a thick layer of cream cheese frosting.',
            'price_cents' => 4800, 'price_unit' => '8-inch cake', 'serves' => '12', 'dietary' => ['contains-nuts'], 'notice_days' => 3,
            'is_featured' => false, 'is_seasonal' => false,
            'image_credit' => 'Photo: Andy Li, CC0, via Wikimedia Commons',
        ],
        [
            'slug' => 'vanilla-cupcakes', 'name' => 'Strawberry Vanilla Cupcakes', 'category' => 'cakes',
            'description' => 'Vanilla bean cupcakes piped high with real-strawberry buttercream. A party in a paper case.',
            'price_cents' => 3600, 'price_unit' => 'dozen', 'serves' => null, 'dietary' => [], 'notice_days' => 3,
            'is_featured' => false, 'is_seasonal' => false,
            'image_credit' => 'Photo: Jess Watters, CC0, via Wikimedia Commons',
        ],
        [
            'slug' => 'chocolate-chip-cookies', 'name' => 'Brown Butter Chocolate Chip Cookies', 'category' => 'cookies-and-bars',
            'description' => 'Crisp edges, chewy middles, pools of dark chocolate and a pinch of sea salt.',
            'price_cents' => 2400, 'price_unit' => 'dozen', 'serves' => null, 'dietary' => [], 'notice_days' => 2,
            'is_featured' => false, 'is_seasonal' => false,
            'image_credit' => 'Photo: Mshuang2, CC0, via Wikimedia Commons',
        ],
        [
            'slug' => 'fudge-brownies', 'name' => 'Fudgy Brownies', 'category' => 'cookies-and-bars',
            'description' => 'Dense, crackle-topped and very chocolatey. Cut into nine generous squares.',
            'price_cents' => 3000, 'price_unit' => 'tray of 9', 'serves' => '9', 'dietary' => [], 'notice_days' => 2,
            'is_featured' => false, 'is_seasonal' => false,
            'image_credit' => 'Photo: Sarah Stierch (Missvain), CC BY 4.0, via Wikimedia Commons',
        ],
        [
            'slug' => 'french-macarons', 'name' => 'French Macarons', 'category' => 'cookies-and-bars',
            'description' => 'Almond shells with ganache and buttercream fillings. Pick a colour theme or let us surprise you.',
            'price_cents' => 3200, 'price_unit' => 'box of 12', 'serves' => null, 'dietary' => ['gluten-free', 'contains-nuts'], 'notice_days' => 4,
            'is_featured' => true, 'is_seasonal' => false,
            'image_credit' => 'Photo: Nicolas Halftermeyer, CC BY-SA 3.0, via Wikimedia Commons',
        ],
        [
            'slug' => 'lattice-apple-pie', 'name' => 'Lattice Apple Pie', 'category' => 'pies-and-tarts',
            'description' => 'Local apples, cinnamon and a hint of lemon under an all-butter lattice crust.',
            'price_cents' => 3400, 'price_unit' => '9-inch pie', 'serves' => '8', 'dietary' => [], 'notice_days' => 3,
            'is_featured' => false, 'is_seasonal' => false,
            'image_credit' => 'Photo: Shisma, CC BY 4.0, via Wikimedia Commons',
        ],
        [
            'slug' => 'lemon-meringue-tartlets', 'name' => 'Lemon Meringue Tartlets', 'category' => 'pies-and-tarts',
            'description' => 'Sharp lemon curd in crisp sweet pastry, crowned with torched Italian meringue.',
            'price_cents' => 3000, 'price_unit' => 'box of 6', 'serves' => null, 'dietary' => [], 'notice_days' => 3,
            'is_featured' => false, 'is_seasonal' => false,
            'image_credit' => 'Photo: claralieu, CC BY 2.0, via Wikimedia Commons',
        ],
        [
            'slug' => 'pumpkin-pie', 'name' => 'Spiced Pumpkin Pie', 'category' => 'pies-and-tarts',
            'description' => 'Roasted pumpkin custard with nutmeg, ginger and clove. Back every autumn until the snow.',
            'price_cents' => 3200, 'price_unit' => '9-inch pie', 'serves' => '8', 'dietary' => [], 'notice_days' => 3,
            'is_featured' => false, 'is_seasonal' => true,
            'image_credit' => 'Photo: Patricia (Brownies for Dinner), CC BY 2.0, via Wikimedia Commons',
        ],
    ];

    /**
     * The About and Contact pages, written for the bakery.
     *
     * @var list<array{slug: string, title: string, sort_order: int, show_in_footer: bool, seo_description: string, body: string}>
     */
    private const array PAGES = [
        [
            'slug' => 'about',
            'title' => 'About us',
            'show_in_footer' => true,
            'sort_order' => 10,
            'seo_description' => 'Meet the home baker behind Hearth & Honey: small batches, real butter and long, slow ferments.',
            'body' => <<<'MARKDOWN'
            ## A bakery that started at the kitchen table

            Hearth & Honey began the way most home bakeries do: a sourdough starter, a lot of
            flour on the floor, and neighbours who kept asking for "one more loaf". Today we bake
            to order from our licensed home kitchen, a few batches a week, so everything that
            leaves the oven is fresh for the day you need it.

            ## How we bake

            - **Slowly.** Our breads ferment for up to 48 hours and our croissants take three days.
            - **With real ingredients.** Cultured butter, local eggs and flour, and fruit in season.
            - **In small batches.** We only bake what has been ordered, so nothing goes to waste.

            ## Allergies and dietary needs

            We offer vegan, dairy-free and gluten-free options, marked on the menu. Our kitchen
            also handles nuts, wheat, dairy and eggs, so we cannot promise a bake is free from
            traces. Tell us about any allergy when you order and we will talk it through.
            MARKDOWN,
        ],
        [
            'slug' => 'contact',
            'title' => 'Contact',
            'show_in_footer' => false,
            'sort_order' => 20,
            'seo_description' => 'Questions about an order, a custom cake or an allergy? Get in touch with Hearth & Honey.',
            'body' => <<<'MARKDOWN'
            Questions about a custom cake, an allergy or a large order? Send us a message and we
            will reply within a day. Ready to order? The [order form](/order) is the quickest way.
            MARKDOWN,
        ],
    ];

    /**
     * Seed the demo bakery.
     */
    public function run(): void
    {
        foreach (self::SETTINGS as $rawKey => $value) {
            $key = SettingKey::from($rawKey);

            Setting::firstOrCreate(['key' => $key->value], ['value' => $key->cast($value)]);
        }

        foreach (self::PAGES as $attributes) {
            Page::firstOrCreate(
                ['slug' => $attributes['slug']],
                [...$attributes, 'is_published' => true],
            );
        }

        foreach (self::MENU as $index => $attributes) {
            $imagePath = 'menu/'.$attributes['slug'].'.webp';

            $this->publishImage($imagePath);

            MenuItem::firstOrCreate(
                ['slug' => $attributes['slug']],
                [...$attributes, 'image_path' => $imagePath, 'is_available' => true, 'sort_order' => $index + 1],
            );
        }

        if (OrderInquiry::query()->exists()) {
            return;
        }

        $this->seedInquiries();
    }

    /**
     * Copy a seeded photo onto the public disk, unless it is already there.
     *
     * Checked per file: the disk is a persistent volume, so after the first
     * boot this is a handful of existence checks, and an image the owner
     * replaced keeps its own path and is never touched.
     */
    private function publishImage(string $path): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return;
        }

        $source = __DIR__.'/images/bakery/'.$path;

        if (is_file($source)) {
            $disk->put($path, (string) file_get_contents($source));
        }
    }

    /**
     * Write a few sample inquiries across every stage, dated around today.
     */
    private function seedInquiries(): void
    {
        $samples = [
            ['Maya Thompson', 'maya.t@example.com', '(555) 201-4432', [['celebration-layer-cake', 1], ['vanilla-cupcakes', 1]], 9, Fulfilment::Pickup, Occasion::Birthday, InquiryStatus::New, null, "It's for my daughter's 7th birthday. Could the cake be pink and purple with \"Happy Birthday Ava\" on top?", null, 0, null],
            ['Daniel Okafor', 'd.okafor@example.com', null, [['butter-croissants', 4], ['cinnamon-rolls', 2]], 4, Fulfilment::Delivery, Occasion::Office, InquiryStatus::New, null, 'Team breakfast for about 30 people. Delivery by 8:30am if possible.', null, 0, null],
            ['Priya Raman', 'priya.raman@example.com', '(555) 377-1904', [['french-macarons', 3]], 12, Fulfilment::Pickup, Occasion::Wedding, InquiryStatus::Quoted, 9800, 'Bridal shower, colours blush and gold.', 'One guest has a sesame allergy.', 1, null],
            ['Tom Becker', 'tom.becker@example.com', '(555) 918-2210', [['country-sourdough', 2], ['rosemary-focaccia', 1]], 3, Fulfilment::Pickup, null, InquiryStatus::Confirmed, 3200, null, null, 2, null],
            ['Chloé Martin', 'chloe.m@example.com', '(555) 640-7713', [['pumpkin-pie', 2], ['lattice-apple-pie', 1]], 6, Fulfilment::Delivery, Occasion::Holiday, InquiryStatus::Confirmed, 10300, 'Thanksgiving dinner for the family.', null, 3, null],
            ['Sam Rivera', 'sam.rivera@example.com', null, [['chocolate-fudge-cake', 1]], -2, Fulfilment::Pickup, Occasion::JustBecause, InquiryStatus::Completed, 5200, null, null, 8, null],
            ['Grace Liu', 'grace.liu@example.com', null, [['chocolate-chip-cookies', 10]], 1, Fulfilment::Delivery, Occasion::Office, InquiryStatus::Declined, null, 'Can you do 120 cookies for tomorrow?', null, 1, 'Fully booked that day. Suggested the following week.'],
        ];

        $menu = MenuItem::query()->get()->keyBy('slug');

        foreach ($samples as [$name, $email, $phone, $lines, $inDays, $fulfilment, $occasion, $status, $quote, $details, $allergies, $daysAgo, $bakerNotes]) {
            $items = [];

            foreach ($lines as [$slug, $quantity]) {
                $item = $menu->get($slug);

                if ($item instanceof MenuItem) {
                    $items[] = [
                        'menu_item_id' => $item->id,
                        'name' => $item->name,
                        'price_unit' => $item->price_unit,
                        'price_cents' => $item->price_cents,
                        'quantity' => $quantity,
                    ];
                }
            }

            if ($items === []) {
                continue;
            }

            $receivedAt = now()->subDays($daysAgo)->setTime(9 + $daysAgo % 9, 10 + $daysAgo * 7 % 50);

            $inquiry = new OrderInquiry([
                'reference' => OrderInquiry::newReference(),
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'fulfilment' => $fulfilment,
                'needed_on' => now()->addDays($inDays)->toDateString(),
                'delivery_address' => $fulfilment === Fulfilment::Delivery ? '42 Birch Lane, Maple Grove' : null,
                'occasion' => $occasion,
                'items' => $items,
                'estimated_total_cents' => (int) collect($items)->sum(fn (array $line): int => $line['price_cents'] * $line['quantity']),
                'details' => $details,
                'allergies' => $allergies,
                'status' => $status,
                'quoted_total_cents' => $quote,
                'baker_notes' => $bakerNotes,
            ]);
            $inquiry->created_at = $receivedAt;
            $inquiry->updated_at = $receivedAt;
            $inquiry->save();
        }
    }
}
