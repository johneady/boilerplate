<?php

namespace Database\Seeders;

use App\Media\MediaCollection;
use App\Media\MediaManager;
use App\Models\PrintLocation;
use App\Models\PrintOrder;
use App\Models\PrintOrderItem;
use App\Prints\Enums\PrintChannel;
use App\Prints\Enums\PrintOrderStatus;
use App\Prints\Enums\PrintPaymentStatus;
use App\Prints\PrintPricing;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

/**
 * A working morning at a photo lab, so the fulfillment console, the panel's
 * order ledger and the dashboard have something true to show the moment the
 * demo opens.
 *
 * Photos are real photographs (Picsum's fixed-seed collection, cached under
 * storage/app so a re-seed does not re-download); if the seeder runs
 * somewhere with no network, it falls back to generated frames so the demo
 * still comes up working.
 *
 * Like DemoBusinessSeeder, no factories: this can run inside the --no-dev
 * image where faker is absent (.ai/rules/seeders.md), so names come from the
 * lists below and the randomness from a fixed seed -- every re-seed shows the
 * same morning, which is exactly what a rehearsed demo wants.
 */
class PrintLabSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const array CUSTOMERS = [
        'Jordan Reyes', 'Maria Salazar', 'The Kim family', 'Devon Whitaker',
        'Priya Anand', 'Sam Okafor', 'Lena Vogt', 'Caleb Doyle',
        'Ruth Meyer', 'Anthony Ferraro', 'Nina Petrov', 'Grace Lindqvist',
    ];

    /**
     * @var list<string>
     */
    private const array STREETS = [
        "14 Kingfisher Lane\nRockport, TX 78382",
        "809 Pearl Street, Apt 3\nFulton, TX 78358",
        "612 Saltgrass Way\nCorpus Christi, TX 78412",
        "3310 Heron Ridge\nAustin, TX 78745",
    ];

    /**
     * The order of the morning: channel, where it stands, how many photos,
     * and how long ago it arrived. The console's waiting tab should tell a
     * story at a glance -- families waiting, orders on the printers, prints
     * bagged for pickup -- and the history tabs should have depth.
     *
     * @var list<array{channel: string, status: string, photos: int, minutes_ago: int}>
     */
    private const array MORNING = [
        ['channel' => 'in_store', 'status' => 'received', 'photos' => 2, 'minutes_ago' => 6],
        ['channel' => 'in_store', 'status' => 'received', 'photos' => 1, 'minutes_ago' => 14],
        ['channel' => 'remote', 'status' => 'received', 'photos' => 3, 'minutes_ago' => 22],
        ['channel' => 'in_store', 'status' => 'received', 'photos' => 3, 'minutes_ago' => 38],
        ['channel' => 'remote', 'status' => 'received', 'photos' => 2, 'minutes_ago' => 51],
        ['channel' => 'in_store', 'status' => 'printing', 'photos' => 2, 'minutes_ago' => 74],
        ['channel' => 'remote', 'status' => 'printing', 'photos' => 4, 'minutes_ago' => 96],
        ['channel' => 'in_store', 'status' => 'ready', 'photos' => 1, 'minutes_ago' => 132],
        ['channel' => 'remote', 'status' => 'ready', 'photos' => 3, 'minutes_ago' => 168],
        ['channel' => 'in_store', 'status' => 'completed', 'photos' => 2, 'minutes_ago' => 1560],
        ['channel' => 'in_store', 'status' => 'completed', 'photos' => 1, 'minutes_ago' => 2760],
        ['channel' => 'remote', 'status' => 'completed', 'photos' => 3, 'minutes_ago' => 4200],
    ];

    /**
     * @var list<string> one path per photo slot, in order
     */
    private array $frames = [];

    public function run(): void
    {
        if (app()->environment('production') || PrintOrder::query()->exists()) {
            return;
        }

        mt_srand(20260927);

        // Attach dispatches the re-encoding job; run it inline so every
        // seeded photo has its public conversions the moment the seeder
        // finishes, rather than depending on a worker being up.
        $previousQueue = config('queue.default');
        config(['queue.default' => 'sync']);

        try {
            $this->seed();
        } finally {
            config(['queue.default' => $previousQueue]);
            Date::setTestNow();
        }
    }

    private function seed(): void
    {
        $locations = $this->seedLocations();

        $photoSlots = array_sum(array_map(
            fn (array $spec): int => $spec['photos'],
            self::MORNING,
        ));

        $this->frames = $this->photoFrames($photoSlots);

        $frameIndex = 0;

        foreach (self::MORNING as $index => $spec) {
            $this->seedOrder($spec, $locations, $index, $frameIndex);
        }
    }

    /**
     * @return array<string, PrintLocation>
     */
    private function seedLocations(): array
    {
        $locations = [];

        foreach ([
            ['slug' => 'front-counter', 'name' => 'Front counter', 'address' => '1122 Wharf Road, Rockport'],
            ['slug' => 'marina-kiosk', 'name' => 'Marina kiosk', 'address' => 'Marina gate, Rockport Harbor'],
        ] as $counter) {
            $location = new PrintLocation;

            $location->forceFill($counter + ['is_active' => true]);
            $location->save();

            $locations[$counter['slug']] = $location;
        }

        return $locations;
    }

    /**
     * @param  array{channel: string, status: string, photos: int, minutes_ago: int}  $spec
     * @param  array<string, PrintLocation>  $locations
     */
    private function seedOrder(array $spec, array $locations, int $index, int &$frameIndex): PrintOrder
    {
        $channel = PrintChannel::from($spec['channel']);
        $status = PrintOrderStatus::from($spec['status']);
        $customer = self::CUSTOMERS[$index % count(self::CUSTOMERS)];
        $inStore = $channel === PrintChannel::InStore;

        Date::setTestNow(now()->subMinutes($spec['minutes_ago']));

        $quantities = [];

        for ($i = 0; $i < $spec['photos']; $i++) {
            // Mostly ones and twos, with the odd three: the deal's shape
            // shows up in the totals without every order being a bundle.
            $quantities[] = [1, 1, 2, 2, 3][mt_rand(0, 4)];
        }

        $quote = PrintPricing::quote(array_sum($quantities));

        $order = new PrintOrder;

        $order->forceFill([
            'code' => PrintOrder::generateCode(),
            'channel' => $channel,
            'print_location_id' => $inStore
                ? $locations[mt_rand(0, 1) === 0 ? 'front-counter' : 'marina-kiosk']->getKey()
                : null,
            'status' => $status,
            'payment_status' => $inStore ? PrintPaymentStatus::PayAtCounter : PrintPaymentStatus::Paid,
            'customer_name' => $customer,
            'customer_email' => $inStore
                ? null
                : str($customer)->camel()->lower()->replaceMatches('/[^a-z]/', '')->append('@example.com')->toString(),
            'customer_phone' => $inStore
                ? '+1 (361) 555-01'.str_pad((string) (10 + $index), 2, '0', STR_PAD_LEFT)
                : null,
            'mailing_address' => $inStore ? null : self::STREETS[$index % count(self::STREETS)],
            'prints_total_cents' => $quote->totalCents,
            'list_total_cents' => $quote->listTotalCents,
            'savings_cents' => $quote->savingsCents,
        ]);

        $order->save();

        $manager = app(MediaManager::class);

        foreach ($quantities as $quantity) {
            $path = $this->frames[$frameIndex] ?? $this->frames[array_key_last($this->frames)];
            $frameIndex++;

            $media = $manager->attach(
                file: new UploadedFile($path, basename($path), 'image/jpeg', test: true),
                collection: MediaCollection::OrderPhoto,
                owner: $order,
            );

            $item = new PrintOrderItem;

            $item->forceFill([
                'print_order_id' => $order->getKey(),
                'media_id' => $media->getKey(),
                'quantity' => $quantity,
                // Anything past "waiting" has been through a printer; the
                // front counter printer is the busy one, as it would be.
                'printer' => $status === PrintOrderStatus::Received ? null
                    : (mt_rand(0, 3) === 0 ? 'lab' : 'front'),
                'printed_at' => $status === PrintOrderStatus::Received ? null : now(),
            ])->save();
        }

        return $order;
    }

    /**
     * Real photographs for the seeded orders, one per photo slot.
     *
     * Picsum's /seed/ URLs are stable for a given seed, so a re-seed shows
     * the same photos. Cached under storage/app/seed-frames so only the
     * first seed touches the network; a fetch that fails writes a generated
     * frame instead, so an offline deploy still seeds.
     *
     * @return list<string>
     */
    private function photoFrames(int $count): array
    {
        $directory = storage_path('app/seed-frames');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $paths = [];

        for ($i = 0; $i < $count; $i++) {
            $path = $directory.'/frame-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'.jpg';

            if (! is_file($path)) {
                $response = Http::timeout(15)->get('https://picsum.photos/seed/harbor-lab-'.$i.'/1200/900.jpg');

                if ($response->successful() && str_starts_with($response->body(), "\xFF\xD8")) {
                    file_put_contents($path, $response->body());
                } else {
                    $this->generateFrame($path, $i);
                }
            }

            $paths[] = $path;
        }

        return $paths;
    }

    /**
     * Write a gradient frame for a photo that could not be fetched.
     */
    private function generateFrame(string $path, int $index): void
    {
        $palettes = [
            [[15, 90, 96], [34, 211, 238]],
            [[19, 78, 74], [94, 234, 212]],
            [[12, 49, 49], [45, 212, 191]],
            [[21, 94, 117], [125, 211, 252]],
            [[49, 46, 129], [129, 140, 248]],
            [[124, 45, 18], [253, 186, 116]],
        ];

        [$from, $to] = $palettes[$index % count($palettes)];

        $image = imagecreatetruecolor(1200, 900);

        foreach (range(0, 1200, 4) as $x) {
            $t = $x / 1200;

            $color = imagecolorallocate(
                $image,
                (int) round($from[0] + ($to[0] - $from[0]) * $t),
                (int) round($from[1] + ($to[1] - $from[1]) * $t),
                (int) round($from[2] + ($to[2] - $from[2]) * $t),
            );

            imagefilledrectangle($image, (int) $x, 0, (int) $x + 4, 900, $color);
        }

        imagejpeg($image, $path, 85);
        imagedestroy($image);
    }
}
