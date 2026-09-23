<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Setting;
use App\Settings\SettingKey;
use Illuminate\Database\Seeder;

/**
 * The demo brokerage's brand, contact details and About page, so the public
 * calculators site comes up as Harbor & Main Realty rather than the
 * boilerplate's placeholder business.
 *
 * Runs on every container boot (docker/entrypoint/entrypoint.sh), so it is
 * idempotent and uses no factories -- faker is absent from the --no-dev
 * production image. See .ai/rules/seeders.md. firstOrCreate keeps an
 * operator's own edits from the admin panel across redeploys.
 *
 * Runs BEFORE SettingsSeeder and PagesSeeder, so these land ahead of their
 * generic placeholders.
 * The calculators' own numbers are not settings: they live in
 * config/calculators.php.
 */
class BrokerageSeeder extends Seeder
{
    /**
     * The brokerage's brand and contact details, keyed by SettingKey value.
     *
     * @var array<string, string>
     */
    private const array SETTINGS = [
        'business_name' => 'Harbor & Main Realty',
        'business_address' => "200 Main Street, Suite 4\nHarbor City",
        'business_phone' => '(555) 010-4477',
        'business_email' => 'hello@harborandmain.example',
        'seo_title' => 'Harbor & Main Realty — Free agent income and split calculators',
        'seo_description' => 'Two free real estate calculators: plan the listings, buyers and appointments behind your income goal, or compare your current commission split with ours. No sign-up.',
    ];

    /**
     * The brokerage's About page, in place of the placeholder PagesSeeder
     * would otherwise create under the same slug.
     *
     * @var array<string, mixed>
     */
    private const array ABOUT_PAGE = [
        'slug' => 'about',
        'title' => 'About',
        'show_in_footer' => true,
        'sort_order' => 10,
        'seo_description' => 'Harbor & Main Realty is an agent-first brokerage with a simple, capped commission split and no royalties.',
        'body' => <<<'MARKDOWN'

        ## An agent-first brokerage

        Harbor & Main Realty was built around one idea: agents should keep more of what they earn.
        Our plan is simple to explain and easy to check, which is why both of our calculators show
        every assumption behind their numbers.

        ## Thinking about a career in real estate?

        The income planner works your income goal back to the listings, buyer clients and weekly
        appointments it takes. It is the same exercise we walk every new agent through in their
        first week.

        ## Already licensed?

        The split comparison puts your current take-home beside what you would keep with us. If the
        numbers make sense, we would be glad to talk. If they do not, you have lost nothing: there
        is no sign-up, and nothing you enter is stored.
        MARKDOWN,
    ];

    /**
     * Seed the brokerage's settings and About page.
     */
    public function run(): void
    {
        foreach (self::SETTINGS as $rawKey => $value) {
            $key = SettingKey::from($rawKey);

            Setting::firstOrCreate(
                ['key' => $key->value],
                ['value' => $key->cast($value)],
            );
        }

        Page::firstOrCreate(
            ['slug' => self::ABOUT_PAGE['slug']],
            [...self::ABOUT_PAGE, 'is_published' => true],
        );
    }
}
