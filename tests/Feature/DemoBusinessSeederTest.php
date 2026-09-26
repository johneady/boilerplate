<?php

use App\Auth\Role;
use App\Models\ContactSubmission;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\User;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\PaymentManager;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DemoBusinessSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed([AdminUserSeeder::class, PlanSeeder::class]);
});

test('a year of demo trading is seeded through the real payment actions', function () {
    Notification::fake();

    $this->seed(DemoBusinessSeeder::class);

    $paid = Payment::query()->whereNotNull('receipt_number')->orderBy('receipt_number')->get();

    expect(app(PaymentManager::class)->enabled())->toBeTrue()
        ->and($paid->count())->toBeGreaterThan(100)
        // Gap-free, and in the order the sales happened.
        ->and($paid->pluck('receipt_number')->all())->toBe(range(1, $paid->count()))
        ->and($paid->pluck('paid_at')->map->getTimestamp()->all())->toBe($paid->pluck('paid_at')->map->getTimestamp()->sort()->values()->all())
        ->and($paid->first()->paid_at->lessThan(now()->subMonths(10)))->toBeTrue()
        ->and(Subscription::query()->where('status', SubscriptionStatus::PastDue)->count())->toBe(1)
        ->and(Subscription::query()->where('status', SubscriptionStatus::Active)->count())->toBeGreaterThan(5)
        ->and(Dispute::query()->count())->toBe(1)
        ->and(ContactSubmission::query()->whereNull('handled_at')->count())->toBe(3);

    // Hundreds of receipts to invented addresses would be a mail storm.
    Notification::assertNothingSent();
});

test('it leaves an installation that has taken payments alone', function () {
    $this->seed(DemoBusinessSeeder::class);
    $count = Payment::query()->count();

    $this->seed(DemoBusinessSeeder::class);

    expect(Payment::query()->count())->toBe($count);
});

test('it never writes demo sales into live mode', function () {
    app(Settings::class)->setMany([
        SettingKey::PaymentsEnabled->value => true,
        SettingKey::PaymentsMode->value => 'live',
        SettingKey::DemoGatewayEnabled->value => true,
    ]);

    $this->seed(DemoBusinessSeeder::class);

    expect(Payment::query()->exists())->toBeFalse()
        ->and(User::query()->withRole(Role::User)->exists())->toBeFalse();
});

test('no refund is dated in the future', function () {
    $this->seed(DemoBusinessSeeder::class);

    expect(PaymentTransaction::query()->where('occurred_at', '>', now())->exists())->toBeFalse();
});

test('it never runs in production', function () {
    app()->detectEnvironment(fn () => 'production');

    try {
        (new DemoBusinessSeeder)->run();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect(Payment::query()->exists())->toBeFalse();
});
