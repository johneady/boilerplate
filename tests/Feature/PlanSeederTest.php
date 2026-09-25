<?php

use App\Livewire\Payments\Pricing;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Payments\Enums\BillingInterval;
use App\Payments\Enums\Currency;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\Support\Payments;

test('once payments are on, the seeded plans fill the pricing page in either currency', function (Currency $currency) {
    $this->seed(PlanSeeder::class);
    Payments::enable(['payments_currency' => $currency->value]);

    $starterMonthly = PlanPrice::whereRelation('plan', 'key', 'starter')
        ->where('currency', $currency->value)
        ->where('interval', BillingInterval::Month->value)
        ->sole();

    $this->get(route('payments.pricing'))
        ->assertOk()
        ->assertSeeInOrder(['Starter', 'Pro', 'Business'])
        ->assertSee($starterMonthly->label())
        ->assertDontSee('There are no plans available at the moment.');
})->with(Currency::cases());

test('a seeded plan can be subscribed to through the Demo gateway straight after a full seed', function () {
    Storage::fake('local');
    Storage::fake('public');
    Notification::fake();

    // DatabaseSeeder mutes model events, so the plans are never synced here:
    // the Demo gateway must take them as they are.
    $this->seed(DatabaseSeeder::class);
    Payments::enable();

    $price = PlanPrice::whereRelation('plan', 'key', 'starter')->where('currency', Currency::CAD->value)->where('interval', BillingInterval::Month->value)->sole();

    Livewire::actingAs(User::where('email', 'test@example.com')->sole())
        ->test(Pricing::class)
        ->call('subscribe', $price->id)
        ->assertRedirect(URL::signedRoute('subscriptions.demo.show', Subscription::sole()));
});

test('an install that already has plans of its own gets no samples', function () {
    // Plans can be switched off but never deleted, so samples added beside
    // somebody's own catalogue could not be cleared away.
    Plan::factory()->create(['key' => 'basic']);

    $this->seed(PlanSeeder::class);

    expect(Plan::pluck('key')->all())->toBe(['basic']);
});

test('re-seeding leaves an existing plan alone, prices included', function () {
    $this->seed(PlanSeeder::class);
    Plan::where('key', 'pro')->sole()->update(['name' => 'Pro (renamed by an admin)']);

    // A redeploy re-runs the seeders.
    $this->seed(PlanSeeder::class);

    expect(Plan::where('key', 'pro')->sole()->name)->toBe('Pro (renamed by an admin)')
        ->and(Plan::count())->toBe(3)
        ->and(PlanPrice::count())->toBe(12);
});

test('the full seed adds the sample plans wherever quick logins are offered', function (string $environment) {
    Storage::fake('local');
    Storage::fake('public');
    app()->detectEnvironment(fn () => $environment);

    $this->seed(DatabaseSeeder::class);

    expect(Plan::pluck('key')->all())->toEqualCanonicalizing(['starter', 'pro', 'business']);
})->with(['local', 'staging', 'demo']);

test('the full seed adds no plans in production', function () {
    Storage::fake('local');
    Storage::fake('public');
    app()->detectEnvironment(fn () => 'production');

    // Run directly: $this->seed() prompts for confirmation in production.
    (new DatabaseSeeder)->run();

    expect(Plan::count())->toBe(0);
});

test('the seeder uses no factory, which production has no faker for', function () {
    // It runs inside the --no-dev image on demo instances, where
    // fakerphp/faker is absent. See .ai/rules/seeders.md.
    expect(file_get_contents(base_path('database/seeders/PlanSeeder.php')))
        ->not->toContain('factory(');
});
