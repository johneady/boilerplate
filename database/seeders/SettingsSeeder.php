<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Settings\SettingKey;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Demo business details, so a fresh instance's footer and settings form
     * have something to show rather than a blank contact block.
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
    ];

    /**
     * Seed the demo business details.
     *
     * The business name is deliberately absent: it already falls back to the
     * application name, so seeding it would only pin today's fallback as a
     * stored row.
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
