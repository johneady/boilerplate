<?php

namespace Database\Seeders;

use App\Jobs\ProcessUploadedImage;
use App\Models\Setting;
use App\Settings\SettingKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use Throwable;

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
     * The background colour of the seeded placeholder icon.
     *
     * Indigo to match the avatar gradients' first entry, so the dummy brand
     * mark and the dummy avatars read as one palette.
     */
    private const PLACEHOLDER_ICON_COLOUR = '#4f46e5';

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

        $this->seedSiteIcon();
    }

    /**
     * Seed a placeholder site icon, so a fresh instance's browser tab and
     * social link previews carry a brand mark rather than the framework's
     * default favicon.
     *
     * The mark is a plain coloured square, generated and pushed through the
     * same processing pipeline as an administrator's upload (dispatchSync runs
     * the job inline), so what the seeder stores is exactly what the settings
     * page would have produced. A row that already exists -- an operator's
     * own icon -- is left alone.
     */
    private function seedSiteIcon(): void
    {
        if (Setting::query()->where('key', SettingKey::SiteIcon->value)->exists()) {
            return;
        }

        try {
            $icon = ImageManager::usingDriver((string) config('images.driver'))
                ->createImage(512, 512)
                ->fill(self::PLACEHOLDER_ICON_COLOUR)
                ->encode(new PngEncoder);
        } catch (Throwable) {
            // The icon is decoration: a deployment whose image driver cannot
            // create one must still finish seeding and come up.
            return;
        }

        // Staged on the private disk like any upload; the job deletes the
        // source once it has written the re-encoded conversions. A failure
        // here (a full disk, say) is reported and skipped rather than thrown:
        // seeding re-runs on every deploy, and an icon that will not generate
        // must not crashloop the container over a decoration.
        $sourcePath = 'uploads/pending/site-icon-'.Str::uuid()->toString();

        Storage::disk('local')->put($sourcePath, (string) $icon);

        try {
            ProcessUploadedImage::dispatchSync(
                sourcePath: $sourcePath,
                conversionSet: 'site-icon',
                targetDirectory: 'site-icon/'.Str::uuid()->toString(),
                settingKey: SettingKey::SiteIcon,
            );
        } catch (Throwable $exception) {
            report($exception);

            Storage::disk('local')->delete($sourcePath);
        }
    }
}
