<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Settings\SettingKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * The demo café: its brand, its menu and its About and Contact copy.
 *
 * Runs on every container boot, so it is idempotent and uses no factories
 * (faker is absent from the production image; see .ai/rules/seeders.md).
 * Everything is firstOrCreate, so the owner's own edits survive a redeploy.
 * Runs BEFORE SettingsSeeder and PagesSeeder so the café's details land ahead
 * of their generic placeholders.
 *
 * The menu photos ship beside this seeder in images/menu and are copied onto
 * the public disk, where photos uploaded in the admin panel also live. Each is
 * credited in its product's image_credit, as the Creative Commons licences
 * require.
 */
class CafeSeeder extends Seeder
{
    /**
     * The café's brand and contact details, keyed by SettingKey value.
     *
     * @var array<string, string>
     */
    private const array SETTINGS = [
        'business_name' => 'Juniper & Rye',
        'business_address' => "214 Juniper Street\nToronto, ON M4M 1A7",
        'business_phone' => '(416) 555-0148',
        'business_email' => 'hello@juniperandrye.example',
        'seo_title' => 'Juniper & Rye — Order fresh bread, pastries and cakes online',
        'seo_description' => 'A neighbourhood café and bakery in Toronto. Order online for pickup in 30 minutes or local delivery.',
        'timezone' => 'America/Toronto',
        // Stored off before BlogSeeder runs, which only switches the blog on
        // when nothing is stored yet: this demo is about ordering.
        'blog_enabled' => '0',
    ];

    /**
     * The menu, in menu order.
     *
     * @var list<array{slug: string, name: string, category: string, description: string, price_cents: int, is_featured: bool, image_credit: string}>
     */
    private const array MENU = [
        [
            'slug' => 'country-sourdough', 'name' => 'Country Sourdough Loaf', 'category' => 'breads', 'price_cents' => 950, 'is_featured' => false,
            'description' => 'Our everyday loaf: wild yeast, a 48-hour ferment and a deep, crackly crust. Keeps for three days.',
            'image_credit' => 'Photo: Agelaia, CC BY 4.0, via Wikimedia Commons',
        ],
        [
            'slug' => 'rosemary-focaccia', 'name' => 'Rosemary Sea Salt Focaccia', 'category' => 'breads', 'price_cents' => 650, 'is_featured' => false,
            'description' => 'A generous slab of pillowy, olive-oil rich focaccia with fresh rosemary and flaky salt.',
            'image_credit' => 'Photo: jeffreyw, CC BY 2.0, via Wikimedia Commons',
        ],
        [
            'slug' => 'butter-croissants', 'name' => 'All-Butter Croissant', 'category' => 'pastries', 'price_cents' => 425, 'is_featured' => true,
            'description' => 'Laminated by hand with cultured butter. Shatteringly crisp outside, honeycomb inside.',
            'image_credit' => 'Photo: Rama, CC BY-SA 2.0 FR, via Wikimedia Commons',
        ],
        [
            'slug' => 'cinnamon-rolls', 'name' => 'Cinnamon Roll', 'category' => 'pastries', 'price_cents' => 525, 'is_featured' => true,
            'description' => 'Soft brioche swirled with brown sugar and Ceylon cinnamon, finished with cream cheese icing.',
            'image_credit' => 'Photo: Pannet, CC BY 4.0, via Wikimedia Commons',
        ],
        [
            'slug' => 'celebration-layer-cake', 'name' => 'Sprinkle Celebration Cake', 'category' => 'cakes', 'price_cents' => 4800, 'is_featured' => true,
            'description' => 'A 6-inch, three-layer vanilla cake with sprinkles inside and out. Serves 10. Add your message in the kitchen notes.',
            'image_credit' => 'Photo: Annie Spratt, CC0, via Wikimedia Commons',
        ],
        [
            'slug' => 'chocolate-fudge-cake', 'name' => 'Chocolate Fudge Cake (slice)', 'category' => 'cakes', 'price_cents' => 750, 'is_featured' => false,
            'description' => 'Dark, moist chocolate sponge with silky fudge frosting and a cloud of whipped cream.',
            'image_credit' => 'Photo: Andy Li, CC0, via Wikimedia Commons',
        ],
        [
            'slug' => 'carrot-cake', 'name' => 'Carrot & Walnut Cake (slice)', 'category' => 'cakes', 'price_cents' => 725, 'is_featured' => false,
            'description' => 'Spiced carrot sponge packed with toasted walnuts under thick cream cheese frosting. Contains nuts.',
            'image_credit' => 'Photo: Andy Li, CC0, via Wikimedia Commons',
        ],
        [
            'slug' => 'vanilla-cupcakes', 'name' => 'Strawberry Vanilla Cupcake', 'category' => 'cakes', 'price_cents' => 450, 'is_featured' => false,
            'description' => 'Vanilla bean cupcake piped high with real-strawberry buttercream.',
            'image_credit' => 'Photo: Jess Watters, CC0, via Wikimedia Commons',
        ],
        [
            'slug' => 'chocolate-chip-cookies', 'name' => 'Brown Butter Chocolate Chip Cookie', 'category' => 'treats', 'price_cents' => 325, 'is_featured' => false,
            'description' => 'Crisp edges, chewy middle, pools of dark chocolate and a pinch of sea salt.',
            'image_credit' => 'Photo: Mshuang2, CC0, via Wikimedia Commons',
        ],
        [
            'slug' => 'fudge-brownies', 'name' => 'Fudgy Brownie', 'category' => 'treats', 'price_cents' => 400, 'is_featured' => false,
            'description' => 'Dense, glossy-topped and properly fudgy, made with 70% dark chocolate.',
            'image_credit' => 'Photo: Sarah Stierch (Missvain), CC BY 4.0, via Wikimedia Commons',
        ],
        [
            'slug' => 'french-macarons', 'name' => 'French Macarons (box of 6)', 'category' => 'treats', 'price_cents' => 1500, 'is_featured' => false,
            'description' => 'Almond shells with raspberry, pistachio, salted caramel, lemon, vanilla and chocolate fillings. Gluten-free.',
            'image_credit' => 'Photo: Nicolas Halftermeyer, CC BY-SA 3.0, via Wikimedia Commons',
        ],
        [
            'slug' => 'lattice-apple-pie', 'name' => 'Lattice Apple Pie (9-inch)', 'category' => 'pies', 'price_cents' => 2800, 'is_featured' => false,
            'description' => 'Ontario apples, cinnamon and a buttery lattice crust. Serves 8.',
            'image_credit' => 'Photo: Shisma, CC BY 4.0, via Wikimedia Commons',
        ],
        [
            'slug' => 'lemon-meringue-tartlets', 'name' => 'Lemon Meringue Tartlet', 'category' => 'pies', 'price_cents' => 625, 'is_featured' => false,
            'description' => 'Sharp lemon curd in a crisp shell under toasted Italian meringue.',
            'image_credit' => 'Photo: claralieu, CC BY 2.0, via Wikimedia Commons',
        ],
        [
            'slug' => 'pumpkin-pie', 'name' => 'Spiced Pumpkin Pie (9-inch)', 'category' => 'pies', 'price_cents' => 2600, 'is_featured' => false,
            'description' => 'Silky, warmly spiced pumpkin custard in an all-butter crust. Back for the autumn.',
            'image_credit' => 'Photo: Patricia (Brownies for Dinner), CC BY 2.0, via Wikimedia Commons',
        ],
    ];

    /**
     * The About and Contact copy, replacing PagesSeeder's placeholders.
     *
     * @var list<array{slug: string, title: string, show_in_footer: bool, sort_order: int, seo_description: string, body: string}>
     */
    private const array PAGES = [
        [
            'slug' => 'about',
            'title' => 'About',
            'show_in_footer' => true,
            'sort_order' => 10,
            'seo_description' => 'A neighbourhood café and bakery on Juniper Street, baking every morning since 2019.',
            'body' => <<<'MARKDOWN'
            Juniper & Rye is a small café and bakery on Juniper Street. We start the ovens at 5 am
            and bake everything on the menu in small batches through the day.

            ## Order ahead, skip the line

            Order online and choose a time from 30 minutes away. Your order is boxed and waiting at
            the counter with your name on it, or we will bring it to you anywhere within 5 km.

            ## Allergies

            Our kitchen handles wheat, dairy, eggs, nuts and sesame. Tell us about any allergy in the
            kitchen notes when you order and we will do our best, but we cannot guarantee anything is
            free from traces.
            MARKDOWN,
        ],
        [
            'slug' => 'contact',
            'title' => 'Contact',
            'show_in_footer' => false,
            'sort_order' => 20,
            'seo_description' => 'Get in touch with Juniper & Rye about an order, a custom cake or catering.',
            'body' => <<<'MARKDOWN'
            Questions about an order, a custom cake or catering for the office? Send us a message and
            we will reply the same day. For today's orders, calling the café is quickest.

            We are open every day, 7:30 am to 5:30 pm.
            MARKDOWN,
        ],
    ];

    /**
     * Seed the café's brand, pages and menu.
     */
    public function run(): void
    {
        foreach (self::SETTINGS as $rawKey => $value) {
            $key = SettingKey::from($rawKey);

            Setting::firstOrCreate(['key' => $key->value], ['value' => $key->cast($value)]);
        }

        foreach (self::PAGES as $attributes) {
            Page::firstOrCreate(['slug' => $attributes['slug']], [...$attributes, 'is_published' => true]);
        }

        foreach (self::MENU as $index => $attributes) {
            $imagePath = 'menu/'.$attributes['slug'].'.webp';

            $this->publishImage($imagePath);

            Product::firstOrCreate(
                ['slug' => $attributes['slug']],
                [...$attributes, 'image_path' => $imagePath, 'is_available' => true, 'sort_order' => $index + 1],
            );
        }
    }

    /**
     * Copy a bundled photo onto the public disk, unless it is already there.
     */
    private function publishImage(string $path): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return;
        }

        $source = __DIR__.'/images/'.$path;

        if (is_file($source)) {
            $disk->put($path, (string) file_get_contents($source));
        }
    }
}
