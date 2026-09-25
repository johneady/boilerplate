<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Payments\Enums\BillingInterval;
use App\Payments\Enums\Currency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    /**
     * Sample plans, so switching payments on shows a working pricing page
     * rather than "There are no plans available at the moment."
     *
     * Each plan is priced monthly and yearly in every currency an
     * installation may charge in, because the pricing page offers only prices
     * in the configured currency and changing that setting must not empty it.
     * Amounts are in cents.
     *
     * DatabaseSeeder runs this only where the quick dev logins are offered, so
     * a production install never starts with plans it did not choose. It is
     * invisible until payments are switched on: the pricing page and the Plans
     * screen are both gated on that setting.
     *
     * @var list<array{key: string, name: string, description: string, features: list<string>, trial_days: int, sort_order: int, prices: array<string, array{month: int, year: int}>}>
     */
    private const array PLANS = [
        [
            'key' => 'starter',
            'name' => 'Starter',
            'description' => 'For individuals getting set up.',
            'features' => ['1 user', '5 projects', 'Email support'],
            'trial_days' => 0,
            'sort_order' => 10,
            'prices' => [
                'CAD' => ['month' => 1900, 'year' => 19000],
                'USD' => ['month' => 1500, 'year' => 15000],
            ],
        ],
        [
            'key' => 'pro',
            'name' => 'Pro',
            'description' => 'For small teams that need more room.',
            'features' => ['5 users', 'Unlimited projects', 'Priority email support'],
            'trial_days' => 14,
            'sort_order' => 20,
            'prices' => [
                'CAD' => ['month' => 4900, 'year' => 49000],
                'USD' => ['month' => 3900, 'year' => 39000],
            ],
        ],
        [
            'key' => 'business',
            'name' => 'Business',
            'description' => 'For growing organisations.',
            'features' => ['Unlimited users', 'Unlimited projects', 'Phone and email support'],
            'trial_days' => 0,
            'sort_order' => 30,
            'prices' => [
                'CAD' => ['month' => 9900, 'year' => 99000],
                'USD' => ['month' => 7900, 'year' => 79000],
            ],
        ],
    ];

    /**
     * Seed the sample plans into an empty catalogue.
     *
     * Any existing plan means the catalogue is somebody's own, and plans can
     * only be switched off, never deleted -- so samples added beside it could
     * not be cleared away. That also makes a redeploy (which re-runs the
     * seeders) a no-op after the first seed, leaving administrators' edits
     * alone. No factories: this runs inside the --no-dev image, which has no
     * faker (.ai/rules/seeders.md).
     */
    public function run(): void
    {
        if (Plan::query()->exists()) {
            return;
        }

        // One transaction for the whole catalogue: a failure part-way would
        // otherwise leave a partial one that every later run then skips.
        DB::transaction(function (): void {
            foreach (self::PLANS as $definition) {
                $plan = Plan::create([
                    'key' => $definition['key'],
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'features' => $definition['features'],
                    'trial_days' => $definition['trial_days'],
                    'taxable' => true,
                    'is_active' => true,
                    'sort_order' => $definition['sort_order'],
                ]);

                foreach ($definition['prices'] as $currency => $amounts) {
                    foreach ($amounts as $interval => $amount) {
                        $plan->prices()->create([
                            'currency' => Currency::from($currency),
                            'amount' => $amount,
                            'interval' => BillingInterval::from($interval),
                            'interval_count' => 1,
                            'is_active' => true,
                        ]);
                    }
                }
            }
        });
    }
}
