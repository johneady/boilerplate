<?php

namespace Database\Seeders;

use App\Auth\DevLoginAccounts;
use App\Models\Enquiry;
use App\Models\Setting;
use App\Models\Vehicle;
use App\Settings\SettingKey;
use App\Voltiva\EnquiryStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * The Voltiva Mobility demo: brand settings, photography and the car range,
 * plus a handful of sample enquiries so the CRM has something in it.
 *
 * Runs on every container boot (docker/entrypoint/entrypoint.sh), so it must
 * be idempotent and must not use factories -- faker is absent from the
 * --no-dev production image. See .ai/rules/seeders.md. Cars are created by
 * slug and never overwritten, so a price edited in the panel survives a
 * redeploy; the sample enquiries only go into an empty table.
 *
 * The photographs ship beside this seeder in images/voltiva and are copied
 * onto the public disk, where panel uploads also live. They are Creative
 * Commons images from Wikimedia Commons standing in for Voltiva's own
 * photography, each credited (image_credit, and the Photo credits page).
 */
class VoltivaSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const array BRAND = [
        'business_name' => 'Voltiva Mobility',
        'business_address' => "Carrer de l'Exemple 12\n07009 Palma, Illes Balears",
        'business_phone' => '+34 971 000 123',
        'business_email' => 'hello@voltiva.example',
        'seo_title' => 'Voltiva Mobility – Electric Cars in Mallorca',
        'seo_description' => 'Compact L6e and L7e electric cars for everyday life in Mallorca, with registration, servicing and finance handled locally.',
        'timezone' => 'Europe/Madrid',
    ];

    /**
     * Every photograph the demo uses, with its credit.
     *
     * @var array<string, string>
     */
    public const array PHOTOS = [
        'site/hero.webp' => 'Photo: Micro Mobility Systems AG, CC BY-SA 4.0, via Wikimedia Commons',
        'site/why-voltiva.webp' => 'Photo: Adam Jones, CC BY-SA 2.0, via Wikimedia Commons',
        'site/batteries.webp' => 'Photo: Twilight Tea, CC BY-SA 3.0, via Wikimedia Commons',
        'site/charging.webp' => 'Photo: Mariordo (Mario Roberto Durán Ortiz), CC BY-SA 4.0, via Wikimedia Commons',
        'site/registration.webp' => 'Photo: Diego Delso, CC BY-SA 4.0, via Wikimedia Commons',
        'site/servicing.webp' => 'Photo: Mxperx, CC BY-SA 4.0, via Wikimedia Commons',
        'site/finance.webp' => 'Photo: Matthias Süßen, CC BY-SA 4.0, via Wikimedia Commons',
        'site/tramuntana.webp' => 'Photo: Geir Hval, CC BY-SA 4.0, via Wikimedia Commons',
        'vehicles/nova.webp' => 'Photo: Calreyn88, CC BY-SA 4.0, via Wikimedia Commons',
        'vehicles/nova-interior.webp' => 'Photo: JustAnotherCarDesigner, CC0, via Wikimedia Commons',
        'vehicles/brisa.webp' => 'Photo: Alexandre Prevot, CC BY-SA 4.0, via Wikimedia Commons',
        'vehicles/brisa-rear.webp' => 'Photo: Alexandre Prevot, CC BY-SA 4.0, via Wikimedia Commons',
        'vehicles/terra.webp' => 'Photo: JustAnotherCarDesigner, CC BY-SA 4.0, via Wikimedia Commons',
        'vehicles/terra-side.webp' => 'Photo: Alexander Migl, CC BY-SA 4.0, via Wikimedia Commons',
        'vehicles/isla.webp' => 'Photo: Micro Mobility Systems AG, CC BY-SA 4.0, via Wikimedia Commons',
        'vehicles/isla-interior.webp' => 'Photo: Micro Mobility Systems AG, CC BY-SA 4.0, via Wikimedia Commons',
    ];

    /**
     * The demo range: two L6e and two L7e cars.
     *
     * @var list<array<string, mixed>>
     */
    private const array VEHICLES = [
        [
            'slug' => 'voltiva-nova',
            'name' => 'Voltiva Nova',
            'category' => 'l6e',
            'tagline' => 'The easy way to get around town.',
            'summary' => 'A friendly two-seater for narrow streets and tight parking – and you can drive it from age 15.',
            'description' => "The Nova is the simplest way into electric driving. It is small enough to park almost anywhere in Palma, quiet enough to leave early without waking the street, and costs pennies a day to run.\n\nIt is an **L6e light quadricycle**, so it is limited to 45 km/h and can be driven with an AM moped licence – ideal for students, second cars and short daily trips.",
            'price_cents' => 949000,
            'monthly_from_cents' => 15900,
            'top_speed_kmh' => 45,
            'range_km' => 75,
            'battery_voltage' => 48,
            'battery_capacity_ah' => 120,
            'battery_kwh' => '5.8',
            'battery_chemistry' => 'LiFePO4',
            'charge_hours' => '4.5',
            'motor_kw' => '6.0',
            'seats' => 2,
            'length_mm' => 2530,
            'width_mm' => 1400,
            'height_mm' => 1530,
            'kerb_weight_kg' => 420,
            'warranty_years' => 2,
            'battery_warranty_years' => 5,
            'equipment' => [
                'Heating and demister',
                'Bluetooth audio',
                'USB-C charging ports',
                'LED daytime running lights',
                'Reversing sensors',
                'Portable charging cable (230V)',
            ],
            'key_benefits' => [
                ['title' => 'Drive it from 15', 'body' => 'An AM moped licence is all you need – perfect for students and young drivers.'],
                ['title' => 'Parks anywhere', 'body' => 'At just over 2.5 metres long, it fits spaces other cars drive past.'],
                ['title' => 'Pennies to run', 'body' => 'A full charge costs about a euro at home – and there is no oil, clutch or exhaust to service.'],
                ['title' => 'Zero-emissions badge', 'body' => 'Qualifies for the DGT “0 emissions” label, for easy access to low-emission zones.'],
            ],
            'comfort' => 'Two proper seats, a heater that clears the screen in seconds, Bluetooth audio for your music and USB-C ports for your phone. Big windows make it bright inside and easy to see out of.',
            'safety' => 'A steel safety cell, disc brakes, seat belts for both occupants and reversing sensors. Built and approved to the EU rules for its class.',
            'faqs' => [
                ['question' => 'Can a 15-year-old really drive it?', 'answer' => 'Yes. As an L6e light quadricycle it can be driven in Spain from age 15 with an AM licence.'],
                ['question' => 'Can I take it on the motorway?', 'answer' => 'No. It is limited to 45 km/h, so it is made for towns, villages and local roads.'],
                ['question' => 'How do I charge it?', 'answer' => 'Plug the supplied cable into any normal household socket. A full charge takes about four and a half hours.'],
            ],
            'image_path' => 'vehicles/nova.webp',
            'gallery' => ['vehicles/nova-interior.webp'],
            'is_featured' => true,
            'sort_order' => 10,
        ],
        [
            'slug' => 'voltiva-brisa',
            'name' => 'Voltiva Brisa',
            'category' => 'l6e',
            'tagline' => 'Slim, smart and made for the city.',
            'summary' => 'Two seats one behind the other make the Brisa the narrowest car on the island – with more range than you will use in a week.',
            'description' => "The Brisa seats two in tandem, which makes it narrow enough to slip through old-town streets and park nose-to-kerb where a normal car cannot.\n\nIts larger battery gives up to 110 km between charges, so most owners plug in just once or twice a week.",
            'price_cents' => 1199000,
            'monthly_from_cents' => 19900,
            'top_speed_kmh' => 45,
            'range_km' => 110,
            'battery_voltage' => 60,
            'battery_capacity_ah' => 150,
            'battery_kwh' => '9.0',
            'battery_chemistry' => 'LiFePO4',
            'charge_hours' => '5.5',
            'motor_kw' => '6.0',
            'seats' => 2,
            'length_mm' => 2430,
            'width_mm' => 1300,
            'height_mm' => 1460,
            'kerb_weight_kg' => 425,
            'warranty_years' => 2,
            'battery_warranty_years' => 5,
            'equipment' => [
                'Heating and demister',
                'Digital instrument display',
                'Bluetooth audio',
                'USB-C charging ports',
                'LED daytime running lights',
                'Portable charging cable (230V)',
            ],
            'key_benefits' => [
                ['title' => 'Narrowest on the island', 'body' => 'Just 1.3 metres wide – made for Palma’s old town and busy village streets.'],
                ['title' => 'A week between charges', 'body' => 'Up to 110 km of range covers most people’s whole week of short trips.'],
                ['title' => 'No car licence needed', 'body' => 'Drive it from age 15 with an AM moped licence.'],
                ['title' => 'Charges at home', 'body' => 'Plug into any normal socket – no wallbox or installation.'],
            ],
            'comfort' => 'The tandem seating gives the passenger their own space behind the driver. The digital display is clear in bright sunshine, and the heater and demister keep winter mornings comfortable.',
            'safety' => 'A rigid safety cell, disc brakes front and rear, and seat belts for both seats. Approved to the EU rules for its class.',
            'faqs' => [
                ['question' => 'Is the rear seat comfortable for an adult?', 'answer' => 'Yes – the passenger sits behind the driver with their own legroom, ideal for trips around town.'],
                ['question' => 'How often will I need to charge?', 'answer' => 'Most owners charge once or twice a week. Up to 110 km of range is plenty for short daily trips.'],
                ['question' => 'Does it have air conditioning?', 'answer' => 'The Brisa has heating and a demister. For air conditioning, look at the Terra or the Isla.'],
            ],
            'image_path' => 'vehicles/brisa.webp',
            'gallery' => ['vehicles/brisa-rear.webp'],
            'is_featured' => true,
            'sort_order' => 20,
        ],
        [
            'slug' => 'voltiva-terra',
            'name' => 'Voltiva Terra',
            'category' => 'l7e',
            'tagline' => 'All the island, none of the fuss.',
            'summary' => 'Room for two, a proper boot and up to 150 km of range – the Terra goes wherever you do in Mallorca.',
            'description' => "The Terra is an **L7e** car, so it can reach 80 km/h and use the main roads between towns. Its 72V 150Ah lithium battery gives up to 150 km on a charge – Palma to Pollença and back, with range to spare.\n\nAir conditioning, a reversing camera and a touchscreen come as standard.",
            'price_cents' => 1449000,
            'monthly_from_cents' => 23900,
            'top_speed_kmh' => 80,
            'range_km' => 150,
            'battery_voltage' => 72,
            'battery_capacity_ah' => 150,
            'battery_kwh' => '10.8',
            'battery_chemistry' => 'LiFePO4',
            'charge_hours' => '7.0',
            'motor_kw' => '12.0',
            'seats' => 2,
            'length_mm' => 2530,
            'width_mm' => 1500,
            'height_mm' => 1570,
            'kerb_weight_kg' => 450,
            'warranty_years' => 3,
            'battery_warranty_years' => 8,
            'equipment' => [
                'Air conditioning',
                'Electric power steering (EPS)',
                '9-inch touchscreen with smartphone mirroring',
                'Reversing camera',
                'Bluetooth audio',
                'USB-C charging ports',
                'LED daytime running lights',
                'Portable charging cable (230V)',
            ],
            'key_benefits' => [
                ['title' => 'Main roads, no problem', 'body' => 'Up to 80 km/h, so it keeps up with traffic between towns.'],
                ['title' => 'More range between charges', 'body' => 'A 72V 150Ah lithium battery gives up to 150 km on a single charge.'],
                ['title' => 'Cool in August', 'body' => 'Air conditioning as standard, because this is Mallorca.'],
                ['title' => 'Light, easy steering', 'body' => 'Electric power steering makes parking and tight streets effortless.'],
            ],
            'comfort' => 'Air conditioning, a 9-inch touchscreen that mirrors your phone for maps and music, and a boot big enough for the weekly shop or two beach bags. The seats are supportive for longer drives.',
            'safety' => 'Reinforced safety cell, driver airbag, disc brakes with regenerative braking, a reversing camera and EU type approval for the L7e class.',
            'faqs' => [
                ['question' => 'Which licence do I need?', 'answer' => 'A B1 licence (from age 16) or a normal B car licence.'],
                ['question' => 'Can I drive it from Palma to the north of the island?', 'answer' => 'Yes. With up to 150 km of range and a top speed of 80 km/h, trips across the island are easy.'],
                ['question' => 'Does air conditioning reduce the range?', 'answer' => 'A little – typically by 10 to 15 percent on a hot day. Our range figures are for mixed island driving.'],
                ['question' => 'How long does the battery last?', 'answer' => 'LiFePO4 batteries are rated for thousands of charges, and the Terra’s battery is covered by an 8-year warranty.'],
            ],
            'image_path' => 'vehicles/terra.webp',
            'gallery' => ['vehicles/terra-side.webp'],
            'is_featured' => true,
            'sort_order' => 30,
        ],
        [
            'slug' => 'voltiva-isla',
            'name' => 'Voltiva Isla',
            'category' => 'l7e',
            'tagline' => 'Iconic design. Everyday electric.',
            'summary' => 'Our most premium car: a front-opening door, a fabric sunroof and up to 177 km of range, at up to 90 km/h.',
            'description' => "The Isla turns heads wherever it goes. Step in through the front door, open the fabric sunroof and drive to the coast on up to 177 km of range.\n\nIt is our fastest and longest-range car, with the most equipment as standard – and still small enough to park across a normal parking space.",
            'price_cents' => 1799000,
            'monthly_from_cents' => 28900,
            'top_speed_kmh' => 90,
            'range_km' => 177,
            'battery_voltage' => 96,
            'battery_capacity_ah' => 150,
            'battery_kwh' => '14.4',
            'battery_chemistry' => 'LiFePO4',
            'charge_hours' => '8.0',
            'motor_kw' => '12.5',
            'seats' => 2,
            'length_mm' => 2519,
            'width_mm' => 1473,
            'height_mm' => 1501,
            'kerb_weight_kg' => 450,
            'warranty_years' => 3,
            'battery_warranty_years' => 8,
            'equipment' => [
                'Air conditioning',
                'Electric power steering (EPS)',
                'Fabric sunroof',
                'Reversing camera',
                'Keyless entry',
                'Bluetooth audio',
                'USB-C charging ports',
                'LED daytime running lights',
                'Portable charging cable (230V)',
            ],
            'key_benefits' => [
                ['title' => 'Up to 177 km per charge', 'body' => 'Our longest range – a whole day exploring the island on one charge.'],
                ['title' => 'Up to 90 km/h', 'body' => 'The fastest car in our range, relaxed on main roads.'],
                ['title' => 'Open-air driving', 'body' => 'A fabric sunroof as standard for Mallorca’s long summers.'],
                ['title' => 'Unmistakable design', 'body' => 'The front-opening door and bubble shape make every trip an occasion.'],
            ],
            'comfort' => 'A bench seat for two with room to spare, air conditioning, a fabric sunroof, keyless entry and a digital display. The front door means you step straight onto the pavement when you park nose-in.',
            'safety' => 'A steel and aluminium safety cell, disc brakes with regenerative braking, reversing camera and EU type approval for the L7e class.',
            'faqs' => [
                ['question' => 'How do you get in through the front?', 'answer' => 'The whole front of the car opens as a door, with the steering column moving with it. Park nose-in and step straight onto the pavement.'],
                ['question' => 'Which licence do I need?', 'answer' => 'A B1 licence (from age 16) or a normal B car licence.'],
                ['question' => 'Can I charge it at a public charger?', 'answer' => 'Yes, with a Type 2 adapter, which we can supply as an accessory. Most owners simply charge at home.'],
            ],
            'image_path' => 'vehicles/isla.webp',
            'gallery' => ['vehicles/isla-interior.webp'],
            'is_featured' => true,
            'sort_order' => 40,
        ],
    ];

    /**
     * Sample enquiries: [name, email, car slug, location, source, status, days ago, finance, registration].
     *
     * @var list<array{string, string, string|null, string, string, string, int, bool, bool}>
     */
    private const array SAMPLE_ENQUIRIES = [
        ['Marta Pons', 'marta.pons@example.test', 'voltiva-terra', 'Sóller', 'vehicle', 'new', 0, true, true],
        ['James Whitfield', 'james.w@example.test', 'voltiva-isla', 'Port d’Andratx', 'finder', 'new', 1, false, true],
        ['Laura Ferrer', 'laura.ferrer@example.test', 'voltiva-nova', 'Palma', 'vehicle', 'contacted', 3, true, true],
        ['Klaus Becker', 'k.becker@example.test', 'voltiva-terra', 'Alcúdia', 'compare', 'test_drive', 6, false, false],
        ['Sophie Martin', 'sophie.martin@example.test', null, 'Inca', 'home', 'quoted', 11, true, true],
        ['Toni Riera', 'toni.riera@example.test', 'voltiva-brisa', 'Palma', 'register', 'won', 18, true, true],
        ['Anna Schmidt', 'anna.schmidt@example.test', 'voltiva-isla', 'Santanyí', 'vehicle', 'lost', 25, false, true],
    ];

    /**
     * Seed the brand, the photographs, the range and the sample enquiries.
     */
    public function run(): void
    {
        foreach (self::BRAND as $rawKey => $value) {
            $key = SettingKey::from($rawKey);

            Setting::firstOrCreate(['key' => $key->value], ['value' => $key->cast($value)]);
        }

        foreach (array_keys(self::PHOTOS) as $path) {
            $this->publishImage($path);
        }

        foreach (self::VEHICLES as $attributes) {
            Vehicle::firstOrCreate(
                ['slug' => $attributes['slug']],
                [
                    ...$attributes,
                    'image_credit' => self::PHOTOS[$attributes['image_path']],
                    'is_published' => true,
                ],
            );
        }

        $this->seedEnquiries();
    }

    /**
     * Copy a photograph onto the public disk, unless it is already there.
     *
     * Checked per file: the disk is a persistent volume, so after the first
     * boot this is a handful of existence checks, and a photo an editor
     * replaced keeps its own path and is never touched.
     */
    private function publishImage(string $path): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return;
        }

        $source = __DIR__.'/images/voltiva/'.$path;

        if (is_file($source)) {
            $disk->put($path, (string) file_get_contents($source));
        }
    }

    /**
     * Write the sample enquiries into an empty table, with their email
     * sequence already advanced as far as their age says it would be -- so
     * the panel's timeline shows sent, scheduled and stopped emails, and the
     * first scheduler run does not mail a burst of example addresses.
     */
    private function seedEnquiries(): void
    {
        // Demo instances only. This seeder runs on every production boot, and
        // "only into an empty table" would put the fake customers back into a
        // live CRM the day the team deletes them.
        if (! app(DevLoginAccounts::class)->enabled() || Enquiry::query()->exists()) {
            return;
        }

        $days = Enquiry::followUpDays();

        foreach (self::SAMPLE_ENQUIRIES as [$name, $email, $slug, $location, $source, $status, $daysAgo, $finance, $registration]) {
            $vehicle = $slug === null ? null : Vehicle::query()->where('slug', $slug)->first();
            $status = EnquiryStatus::from($status);
            $createdAt = now()->subDays($daysAgo)->setTime(9 + $daysAgo % 9, 10 + $daysAgo);
            $sent = count(array_filter($days, fn (int $day): bool => $day <= $daysAgo));

            $enquiry = new Enquiry([
                'name' => $name,
                'email' => $email,
                'phone' => '+34 6'.str_pad((string) (crc32($email) % 100000000), 8, '0', STR_PAD_LEFT),
                'vehicle_id' => $vehicle?->id,
                'vehicle_name' => $vehicle?->name,
                'location' => $location,
                'driving_needs' => $daysAgo % 2 === 0 ? ['town', 'commute'] : ['coast_mountain', 'island_trips'],
                'finance_interest' => $finance,
                'registration_interest' => $registration,
                'message' => $vehicle === null
                    ? 'We are thinking of replacing our second car with something electric. What would you suggest?'
                    : "Could I arrange a test drive of the {$vehicle->name}?",
                'source' => $source,
                'locale' => 'en',
                'status' => $status,
            ]);
            $enquiry->created_at = $createdAt;
            $enquiry->updated_at = $createdAt;
            $enquiry->follow_up_step = $sent;
            $enquiry->next_follow_up_at = $status->receivesFollowUps() && isset($days[$sent])
                ? $createdAt->addDays($days[$sent])
                : null;
            // Set so the model's creating hook, which starts a fresh
            // enquiry's sequence, leaves this historic one alone.
            $enquiry->save();
        }
    }
}
