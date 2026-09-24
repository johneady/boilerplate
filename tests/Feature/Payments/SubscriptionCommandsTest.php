<?php

use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Payments\TrialEnding;
use App\Payments\Drivers\DemoDriver;
use App\Payments\Enums\SubscriptionStatus;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
    Notification::fake();
});

test('a trial reminder goes out once, three days before the trial ends', function () {
    $soon = Subscription::factory()->status(SubscriptionStatus::Trialing)->create(['trial_ends_at' => now()->addDays(2)]);
    $later = Subscription::factory()->status(SubscriptionStatus::Trialing)->create(['trial_ends_at' => now()->addDays(5)]);
    $paying = Subscription::factory()->create(['trial_ends_at' => now()->addDay()]);
    $cancelled = Subscription::factory()->status(SubscriptionStatus::Trialing)->create(['trial_ends_at' => now()->addDay(), 'cancel_at_period_end' => true]);

    $this->artisan('payments:notify-trials-ending')->assertSuccessful();
    $this->artisan('payments:notify-trials-ending')->assertSuccessful();

    Notification::assertSentToTimes($soon->user, TrialEnding::class, 1);
    Notification::assertNotSentTo($later->user, TrialEnding::class);
    Notification::assertNotSentTo($paying->user, TrialEnding::class);
    Notification::assertNotSentTo($cancelled->user, TrialEnding::class);
});

test('an unfinished subscription checkout is expired after a day, freeing the user to try again', function () {
    $user = User::factory()->create();
    $abandoned = Payments::subscribe($user, PlanPrice::factory()->create());
    $recent = Payments::subscribe(User::factory()->create(), PlanPrice::factory()->create());
    $this->travelTo($abandoned->created_at->addHours(25));
    $recent->forceFill(['created_at' => now()])->save();

    $this->artisan('payments:end-subscriptions')->assertSuccessful();

    expect($abandoned->fresh())
        ->status->toBe(SubscriptionStatus::Expired)
        ->active_user_id->toBeNull()
        ->and($recent->fresh()->status)->toBe(SubscriptionStatus::Incomplete);
});

test('a checkout completed at the last moment is kept rather than expired', function () {
    $subscription = Payments::subscribe(User::factory()->create(), PlanPrice::factory()->create());
    app(DemoDriver::class)->simulateSubscriber($subscription, 'approve');
    $this->travel(25)->hours();

    $this->artisan('payments:end-subscriptions')->assertSuccessful();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
});

test('a subscription whose return and webhooks were lost is picked up by the stale reconciliation', function () {
    $subscription = Payments::subscribe(User::factory()->create(), PlanPrice::factory()->create());
    app(DemoDriver::class)->simulateSubscriber($subscription, 'approve');
    $this->travel(20)->minutes();

    $this->artisan('payments:reconcile-stale')->assertSuccessful();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->payments()->count())->toBe(1);
});
