<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Settings\SettingKey;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Demo business details and SEO copy, so a fresh instance's footer,
     * settings form and page head have something to show rather than blanks.
     *
     * Public and placeholder, like the seeded accounts: a real deployment
     * replaces them from the admin panel's settings page. Keyed by SettingKey
     * value, since an enum instance cannot be an array key.
     *
     * @var array<string, string>
     */
    private const DEMO_DETAILS = [
        'business_address' => "123 Example Street\nAnytown, ST 12345",
        'business_phone' => '+1 (555) 123-4567',
        'business_email' => 'hello@example.com',
        'seo_title' => 'Cromulent Widgets',
        'seo_description' => 'Quality example widgets, made and shipped from Anytown. Replace this text from the admin panel\'s SEO & brand settings.',
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
