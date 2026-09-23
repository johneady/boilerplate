<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Package;
use App\Models\Setting;
use App\Settings\SettingKey;
use App\Shop\OrderStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * The demo catalogue of drone footage packages, plus a few sample orders so the
 * admin panel's order list has something in it.
 *
 * Runs on every container boot (docker/entrypoint/entrypoint.sh), so it must be
 * idempotent and must not use factories -- faker is absent from the --no-dev
 * production image. See .ai/rules/seeders.md. Packages are created by slug and
 * never overwritten, so an administrator's edits to price or stock survive a
 * redeploy; the sample orders are only written into an empty orders table.
 *
 * The cover photos ship beside this seeder in images/packages and are copied
 * onto the public disk, where uploads made in the admin panel also live -- one
 * place for every package image, so the panel's upload field can show and keep
 * a seeded one. Each is credited in its package's image_credit, as the photos'
 * Creative Commons licences require.
 */
class ShopSeeder extends Seeder
{
    /**
     * The demo catalogue.
     *
     * @var list<array<string, mixed>>
     */
    private const array PACKAGES = [
        [
            'slug' => 'santorini-caldera',
            'title' => 'Santorini Caldera at Golden Hour',
            'location' => 'Santorini',
            'country' => 'Greece',
            'region' => 'europe',
            'summary' => 'Sweeping passes over the whitewashed cliff villages of Imerovigli and Oia as the sun drops into the Aegean.',
            'description' => "Shot over three evenings from the caldera rim, this package follows the light from late afternoon to blue hour.\n\n- Slow reveal over Skaros Rock\n- Orbit of the Imerovigli cliff line\n- High-altitude pull-back across the caldera\n- Sunset tracking shot along the Oia coastline\n\nIdeal for travel campaigns, hotel and villa marketing, and destination wedding films.",
            'price_cents' => 24900,
            'resolution' => '5.4K',
            'frame_rate' => 30,
            'clip_count' => 12,
            'duration_seconds' => 486,
            'stock' => null,
            'is_featured' => true,
            'image_path' => 'packages/santorini-caldera.jpg',
            'image_credit' => 'Photo: Anna Tsolidou, CC BY-SA 4.0, via Wikimedia Commons',
            'sort_order' => 1,
        ],
        [
            'slug' => 'icelandic-volcano',
            'title' => 'Icelandic Volcano Eruption',
            'location' => 'Litli-Hrútur, Reykjanes',
            'country' => 'Iceland',
            'region' => 'europe',
            'summary' => 'Rare close-range footage of an active fissure eruption — lava fountains, steam and the glowing crater rim.',
            'description' => "Captured under permit during the 2023 Reykjanes eruption. Limited licences are sold so the footage stays exclusive.\n\n- Low pass over the active lava channel\n- Top-down shot of the fountaining crater\n- Steam plume reveal with the Keilir volcano behind\n\nSuited to documentaries, science broadcasting and high-impact advertising.",
            'price_cents' => 59900,
            'resolution' => '4K UHD',
            'frame_rate' => 60,
            'clip_count' => 8,
            'duration_seconds' => 312,
            'stock' => 3,
            'is_featured' => true,
            'image_path' => 'packages/icelandic-volcano.jpg',
            'image_credit' => 'Photo: Giles Laurent, CC BY-SA 4.0, via Wikimedia Commons',
            'sort_order' => 2,
        ],
        [
            'slug' => 'great-barrier-reef',
            'title' => 'Great Barrier Reef Shallows',
            'location' => 'Whitsundays, Queensland',
            'country' => 'Australia',
            'region' => 'oceania',
            'summary' => 'Turquoise reef flats and coral bommies seen from above, shot at low tide for maximum colour and clarity.',
            'description' => "Filmed at low tide on a calm morning, when the reef's patterns are sharpest from the air.\n\n- Top-down drift over the coral gardens\n- Horizon reveal across the outer reef\n- Tracking shot following a reef channel\n\nPerfect for conservation stories, tourism campaigns and ocean-themed brands.",
            'price_cents' => 29900,
            'resolution' => '4K UHD',
            'frame_rate' => 30,
            'clip_count' => 10,
            'duration_seconds' => 402,
            'stock' => 25,
            'is_featured' => true,
            'image_path' => 'packages/great-barrier-reef.jpg',
            'image_credit' => 'Photo: Ank Kumar, CC BY-SA 4.0, via Wikimedia Commons',
            'sort_order' => 3,
        ],
        [
            'slug' => 'lake-bled',
            'title' => 'Lake Bled Island Church',
            'location' => 'Bled',
            'country' => 'Slovenia',
            'region' => 'europe',
            'summary' => 'The fairytale island church and castle cliff, framed by the Julian Alps on a still summer morning.',
            'description' => "A calm-water shoot with mirror reflections of the island and the Julian Alps.\n\n- Orbit of the Church of the Assumption\n- Low glide across the lake toward the castle\n- Rising reveal of the Alps behind the town\n\nA favourite for wedding films, travel vlogs and European tourism spots.",
            'price_cents' => 19900,
            'resolution' => '4K UHD',
            'frame_rate' => 30,
            'clip_count' => 9,
            'duration_seconds' => 354,
            'stock' => null,
            'is_featured' => false,
            'image_path' => 'packages/lake-bled.jpg',
            'image_credit' => 'Photo: Tom Salmon, CC BY 2.0, via Wikimedia Commons',
            'sort_order' => 4,
        ],
        [
            'slug' => 'wrangell-glacier',
            'title' => 'Alaskan Glacier Flyover',
            'location' => 'Wrangell–St. Elias',
            'country' => 'United States',
            'region' => 'north-america',
            'summary' => 'A vast river of ice winding between rugged peaks in the largest national park in the United States.',
            'description' => "Remote wilderness footage flown along the length of a valley glacier.\n\n- Long tracking shot down the glacier's centre line\n- Crevasse field top-down\n- Peak-to-valley reveal at the glacier's terminus\n\nGreat for climate stories, outdoor brands and nature documentaries.",
            'price_cents' => 34900,
            'resolution' => '4K UHD',
            'frame_rate' => 30,
            'clip_count' => 11,
            'duration_seconds' => 528,
            'stock' => null,
            'is_featured' => false,
            'image_path' => 'packages/wrangell-glacier.jpg',
            'image_credit' => 'Photo: U.S. Fish and Wildlife Service, public domain, via Wikimedia Commons',
            'sort_order' => 5,
        ],
        [
            'slug' => 'cape-town-lions-head',
            'title' => "Cape Town & Lion's Head at Dusk",
            'location' => 'Cape Town',
            'country' => 'South Africa',
            'region' => 'africa',
            'summary' => "City lights coming on beneath Lion's Head as cloud rolls off Table Mountain and over the Atlantic seaboard.",
            'description' => "A dusk-to-night sequence over one of the world's most dramatic city skylines.\n\n- Rise over Signal Hill toward Lion's Head\n- Cloud-waterfall pass along Table Mountain\n- Night hyperlapse of the city bowl\n\nFor real-estate marketing, city promos and hospitality brands.",
            'price_cents' => 22900,
            'resolution' => '4K UHD',
            'frame_rate' => 24,
            'clip_count' => 10,
            'duration_seconds' => 390,
            'stock' => 12,
            'is_featured' => false,
            'image_path' => 'packages/cape-town-lions-head.jpg',
            'image_credit' => 'Photo: Marcelo Novais, CC0, via Wikimedia Commons',
            'sort_order' => 6,
        ],
        [
            'slug' => 'tuscan-countryside',
            'title' => 'Tuscan Countryside Patchwork',
            'location' => 'Val di Chiana, Tuscany',
            'country' => 'Italy',
            'region' => 'europe',
            'summary' => 'Rolling green and gold fields under a dramatic storm sky — the classic Tuscan patchwork from above.',
            'description' => "Farmland textures and moody skies filmed ahead of a summer storm.\n\n- High-altitude patchwork top-down\n- Slow push over a cypress-lined road\n- Storm-front reveal across the valley\n\nA natural fit for food and wine brands, agriculture and lifestyle content.",
            'price_cents' => 17900,
            'resolution' => '4K UHD',
            'frame_rate' => 30,
            'clip_count' => 8,
            'duration_seconds' => 296,
            'stock' => null,
            'is_featured' => false,
            'image_path' => 'packages/tuscan-countryside.jpg',
            'image_credit' => 'Photo: Sandro Mattei, CC0, via Wikimedia Commons',
            'sort_order' => 7,
        ],
        [
            'slug' => 'maldives-atolls',
            'title' => 'Maldives Atolls from the Clouds',
            'location' => 'North Malé Atoll',
            'country' => 'Maldives',
            'region' => 'asia',
            'summary' => 'Ribbon-thin islands and lagoons scattered across the Indian Ocean, seen from high above the clouds.',
            'description' => "High-altitude footage of the atoll chain on a clear tropical day.\n\n- Top-down pass over island ribbons\n- Cloud-layer reveal of the lagoon\n- Descending shot toward a resort island\n\nThis limited package has sold out — join the waitlist from the contact page.",
            'price_cents' => 27900,
            'resolution' => '4K UHD',
            'frame_rate' => 30,
            'clip_count' => 7,
            'duration_seconds' => 268,
            'stock' => 0,
            'is_featured' => false,
            'image_path' => 'packages/maldives-atolls.jpg',
            'image_credit' => 'Photo: Nattu Adnan, CC0, via Wikimedia Commons',
            'sort_order' => 8,
        ],
    ];

    /**
     * The demo shop's brand, keyed by SettingKey value.
     *
     * Seeded BEFORE SettingsSeeder (see DatabaseSeeder) and with firstOrCreate
     * like it, so these win over its generic placeholders on a fresh database
     * while an administrator's own values still survive every redeploy.
     *
     * @var array<string, string>
     */
    private const array BRAND = [
        'business_name' => 'SkyReel Aerials',
        'business_address' => "Studio 4, 88 Harbour Road\nVancouver, BC V6B 1A1",
        'business_phone' => '+1 (604) 555-0142',
        'business_email' => 'hello@skyreel.example',
        'seo_title' => 'SkyReel Aerials — Licensed Drone Footage',
        'seo_description' => 'Cinematic 4K and 5.4K drone footage packages from iconic locations worldwide, with a royalty-free commercial licence and instant download.',
    ];

    /**
     * Sample orders, as [customer name, email, package slugs, status, days ago].
     *
     * @var list<array{string, string, list<string>, string, int}>
     */
    private const array SAMPLE_ORDERS = [
        ['Maya Okafor', 'maya@northlight-films.example', ['santorini-caldera', 'lake-bled'], 'fulfilled', 12],
        ['Daniel Reyes', 'dan@reyes-travel.example', ['great-barrier-reef'], 'fulfilled', 9],
        ['Priya Nair', 'priya@bluewave-agency.example', ['icelandic-volcano'], 'fulfilled', 6],
        ['Tom Becker', 'tom@becker-weddings.example', ['lake-bled', 'tuscan-countryside'], 'refunded', 4],
        ['Aisha Rahman', 'aisha@cityscape-realty.example', ['cape-town-lions-head'], 'paid', 1],
        ['Lucas Moreau', 'lucas@moreau-docs.example', ['wrangell-glacier', 'great-barrier-reef'], 'paid', 0],
    ];

    /**
     * Seed the catalogue and, into an empty orders table, the sample orders.
     */
    public function run(): void
    {
        foreach (self::BRAND as $rawKey => $value) {
            $key = SettingKey::from($rawKey);

            Setting::firstOrCreate(['key' => $key->value], ['value' => $key->cast($value)]);
        }

        foreach (self::PACKAGES as $attributes) {
            $this->publishImage($attributes['image_path']);

            Package::firstOrCreate(
                ['slug' => $attributes['slug']],
                [...$attributes, 'is_active' => true],
            );
        }

        if (Order::query()->exists()) {
            return;
        }

        foreach (self::SAMPLE_ORDERS as [$name, $email, $slugs, $status, $daysAgo]) {
            $this->seedOrder($name, $email, $slugs, OrderStatus::from($status), $daysAgo);
        }
    }

    /**
     * Copy a seeded cover photo onto the public disk, unless it is already there.
     *
     * Checked per file rather than per run: the disk is a persistent volume, so
     * after the first boot this is a handful of existence checks, and an image
     * an administrator replaced keeps its own path and is never touched.
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

    /**
     * Write one sample order without touching stock.
     *
     * Built directly rather than through App\Shop\Checkout, which would
     * decrement the stock figures the catalogue above deliberately sets.
     *
     * @param  list<string>  $slugs
     */
    private function seedOrder(string $name, string $email, array $slugs, OrderStatus $status, int $daysAgo): void
    {
        $packages = Package::query()->whereIn('slug', $slugs)->get();

        if ($packages->isEmpty()) {
            return;
        }

        $placedAt = now()->subDays($daysAgo)->setTime(10 + $daysAgo % 8, 15 + $daysAgo * 3);

        $order = new Order([
            'reference' => Order::newReference(),
            'customer_name' => $name,
            'customer_email' => $email,
            'status' => $status,
            'total_cents' => (int) $packages->sum('price_cents'),
            'fulfilled_at' => $status === OrderStatus::Fulfilled ? $placedAt->addHour() : null,
        ]);
        $order->created_at = $placedAt;
        $order->updated_at = $placedAt;
        $order->save();

        foreach ($packages as $package) {
            $order->items()->create([
                'package_id' => $package->id,
                'package_title' => $package->title,
                'price_cents' => $package->price_cents,
            ]);
        }
    }
}
