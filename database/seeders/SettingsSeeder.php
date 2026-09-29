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
     * @var array<string, string|bool>
     */
    private const DEMO_DETAILS = [
        'business_name' => 'Sillage',
        'business_address' => "Montréal, QC\nCanada",
        'business_email' => 'hello@sillage.example',
        'seo_title' => 'Sillage — the open perfume database',
        'seo_description' => 'Search thousands of perfumes by house, perfumer and note. Follow the fragrances you love and see what the community is wearing.',
        // Members sign up to follow perfumes, so registration is on here
        // even though the boilerplate ships with it off.
        'allow_registration' => true,
        'timezone' => 'America/Toronto',
    ];

    /**
     * Seed the demo details.
     *
     * The indexing toggle is deliberately absent: it already defaults to on,
     * so seeding it would only pin today's fallback as a stored row.
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
