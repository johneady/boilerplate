<?php

use App\Models\Subscription;
use App\Models\User;
use App\Payments\Enums\SubscriptionStatus;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
});

test('access follows the subscription\'s status', function (SubscriptionStatus $status, array $attributes, bool $access) {
    $user = User::factory()->create();
    Subscription::factory()->for($user)->status($status)->create($attributes);

    expect($user->subscribed())->toBe($access);
})->with([
    'trialing' => [SubscriptionStatus::Trialing, [], true],
    'active' => [SubscriptionStatus::Active, [], true],
    'active, ending later' => [SubscriptionStatus::Active, ['cancel_at_period_end' => true, 'ends_at' => fn () => now()->addDay()], true],
    'active, but its end has passed' => [SubscriptionStatus::Active, ['cancel_at_period_end' => true, 'ends_at' => fn () => now()->subMinute()], false],
    'past due, within grace' => [SubscriptionStatus::PastDue, ['past_due_since' => fn () => now()->subDays(6)], true],
    'past due, grace over' => [SubscriptionStatus::PastDue, ['past_due_since' => fn () => now()->subDays(7)->subMinute()], false],
    'canceled' => [SubscriptionStatus::Canceled, ['ends_at' => fn () => now()->subMinute()], false],
    'incomplete' => [SubscriptionStatus::Incomplete, [], false],
    'expired' => [SubscriptionStatus::Expired, [], false],
]);

test('the grace period follows the setting, and none at all is allowed', function () {
    $user = User::factory()->create();
    Subscription::factory()->for($user)->status(SubscriptionStatus::PastDue)->create(['past_due_since' => now()->subHour()]);

    app(Settings::class)->set(SettingKey::PastDueGraceDays, 0);
    expect($user->subscribed())->toBeFalse();

    app(Settings::class)->set(SettingKey::PastDueGraceDays, 1);
    expect($user->subscribed())->toBeTrue();
});

test('a grace period cannot be stretched past its bounds by a hand-edited setting', function (mixed $stored, int $days) {
    expect(SettingKey::PastDueGraceDays->cast($stored))->toBe($days);
})->with([
    ['9999', 60],
    ['-3', 7],
    ['soon', 7],
    [14, 14],
]);

test('one user\'s subscription gives nobody else access', function () {
    Subscription::factory()->create();

    expect(User::factory()->create()->subscribed())->toBeFalse();
});
