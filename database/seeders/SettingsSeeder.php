<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Settings\SettingKey;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Demo business details, SEO copy and display timezone, so a fresh
     * instance's footer, settings form and page head have something to show
     * rather than blanks.
     *
     * Public and placeholder, like the seeded accounts: a real deployment
     * replaces them from the admin panel's settings page. Keyed by SettingKey
     * value, since an enum instance cannot be an array key.
     *
     * @var array<string, string>
     */
    private const DEMO_DETAILS = [
        'business_address' => "1122 Wharf Road\nRockport, TX 78382",
        'business_phone' => '+1 (361) 555-0184',
        'business_email' => 'counter@harborphotolab.com',
        'seo_title' => 'Harbor Photo Lab — phone photos, real prints',
        'seo_description' => 'Scan, choose, collect. Photo prints from your phone in minutes at our Rockport counter, or posted anywhere in the States.',
        // A named zone rather than the bare UTC default: the demo details are
        // American, and a regional identifier demonstrates the setting better
        // than the storage timezone would.
        'timezone' => 'America/Chicago',
    ];

    /**
     * Seed the demo details.
     *
     * The business name and the indexing toggle are deliberately absent: the
     * name already falls back to the application name, and indexing to on, so
     * seeding either would only pin today's fallback as a stored row.
     */
    public function run(): void
    {
        foreach (self::DEMO_DETAILS as $rawKey => $value) {
            $key = SettingKey::from($rawKey);

            // firstOrCreate rather than the Settings service, which always
            // writes: seeding may re-run on every deploy, and an operator's
            // own details must survive that the way their password does.
            Setting::firstOrCreate(
                ['key' => $key->value],
                ['value' => $key->cast($value)],
            );
        }

    }
}
