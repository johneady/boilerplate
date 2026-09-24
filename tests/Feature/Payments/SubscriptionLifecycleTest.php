<?php

use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\User;
use App\Notifications\Payments\PaymentReceipt;
use App\Notifications\Payments\SubscriptionCanceled;
use App\Notifications\Payments\SubscriptionPaymentFailed;
use App\Notifications\Payments\SubscriptionRenewed;
use App\Notifications\Payments\SubscriptionStarted;
use App\Payments\Actions\CancelSubscription;
use App\Payments\Actions\ReconcileSubscription;
use App\Payments\Actions\RefundPayment;
use App\Payments\Actions\ResumeSubscription;
use App\Payments\Actions\SwapSubscriptionPlan;
use App\Payments\Data\GatewaySubscriptionState;
use App\Payments\Drivers\DemoDriver;
use App\Payments\Enums\Currency;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable(['ops_alert_email' => 'ops@example.test']);
    Notification::fake();
});

function proPrice(int $trialDays = 0): PlanPrice
{
    TaxRate::factory()->rate('HST', '13')->create();

    return PlanPrice::factory()->for(Plan::factory()->trial($trialDays)->state(['key' => 'pro', 'name' => 'Pro']))->create(['amount' => 2900]);
}

function renew(Subscription $subscription): Subscription
{
    app(DemoDriver::class)->simulateRenewal($subscription);

    return app(ReconcileSubscription::class)->handle($subscription, TransactionSource::Demo);
}

test('subscribing starts the subscription and records its first payment with tax', function () {
    $user = User::factory()->create();

    $subscription = Payments::subscribeWithDemo($user, proPrice());

    $payment = $subscription->payments()->sole();

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->active_user_id)->toBe($user->id)
        ->and($subscription->current_period_end->isFuture())->toBeTrue()
        ->and($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->user_id)->toBe($user->id)
        ->and($payment->subtotal)->toBe(2900)
        ->and($payment->tax_total)->toBe(377)
        ->and($payment->amount_captured)->toBe(3277)
        ->and($payment->tax_lines)->toEqualCanonicalizing([['name' => 'HST', 'percentage' => '13.000', 'amount' => 377]])
        ->and($user->subscribed())->toBeTrue()
        ->and($user->subscribed('pro'))->toBeTrue()
        ->and($user->subscribed('team'))->toBeFalse();

    Notification::assertSentTo($user, SubscriptionStarted::class);
    Notification::assertSentOnDemand(SubscriptionRenewed::class, fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === $user->email);
    Notification::assertNotSentTo($user, PaymentReceipt::class);
});

test('a plan with a trial starts trialing and charges nothing yet', function () {
    $user = User::factory()->create();

    $subscription = Payments::subscribeWithDemo($user, proPrice(trialDays: 14));

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->trial_ends_at->isSameDay(now()->addDays(14)))->toBeTrue()
        ->and($subscription->payments()->count())->toBe(0)
        ->and($user->subscribed())->toBeTrue();
});

test('each renewal is recorded once however many times it is reconciled', function () {
    $user = User::factory()->create();
    $subscription = renew(Payments::subscribeWithDemo($user, proPrice()));

    foreach (range(1, 3) as $attempt) {
        app(ReconcileSubscription::class)->handle($subscription, TransactionSource::Webhook);
    }

    expect($subscription->payments()->count())->toBe(2)
        ->and($subscription->payments()->pluck('amount_captured')->all())->toBe([3277, 3277]);

    Notification::assertSentTimes(SubscriptionStarted::class, 1);
    Notification::assertSentOnDemandTimes(SubscriptionRenewed::class, 2);
});

test('a subscription payment can be refunded like any other', function () {
    $subscription = Payments::subscribeWithDemo(User::factory()->create(), proPrice());
    $payment = $subscription->payments()->sole();

    app(RefundPayment::class)->handle($payment, Money::of(1000, Currency::CAD), 'refund:test');

    expect($payment->fresh())
        ->status->toBe(PaymentStatus::PartiallyRefunded)
        ->amount_refunded->toBe(1000);
});

test('a failed renewal makes the subscription past due and tells the subscriber and operators once', function () {
    $user = User::factory()->create();
    $subscription = Payments::subscribeWithDemo($user, proPrice());

    app(DemoDriver::class)->simulateFailedRenewal($subscription);
    $subscription = app(ReconcileSubscription::class)->handle($subscription, TransactionSource::Demo);
    app(ReconcileSubscription::class)->handle($subscription, TransactionSource::Webhook);

    expect($subscription->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription->past_due_since)->not->toBeNull();

    Notification::assertSentToTimes($user, SubscriptionPaymentFailed::class, 1);
    Notification::assertSentOnDemand(SubscriptionPaymentFailed::class, fn (SubscriptionPaymentFailed $notification, $channels, $notifiable): bool => $notification->forOperators
        && $notifiable->routes['mail'] === 'ops@example.test');
});

test('a past-due subscriber keeps access for the grace period only', function () {
    $user = User::factory()->create();
    $subscription = Payments::subscribeWithDemo($user, proPrice());
    app(DemoDriver::class)->simulateFailedRenewal($subscription);
    app(ReconcileSubscription::class)->handle($subscription, TransactionSource::Demo);

    $this->travel(6)->days();
    expect($user->subscribed())->toBeTrue();

    $this->travel(2)->days();
    expect($user->subscribed())->toBeFalse();
});

test('a successful retry closes the grace period', function () {
    $user = User::factory()->create();
    $subscription = Payments::subscribeWithDemo($user, proPrice());
    app(DemoDriver::class)->simulateFailedRenewal($subscription);
    app(ReconcileSubscription::class)->handle($subscription, TransactionSource::Demo);

    $subscription = renew($subscription);

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->past_due_since)->toBeNull();
});

test('a cancellation at period end keeps access until then, can be taken back, and ends on schedule', function () {
    $user = User::factory()->create();
    $subscription = Payments::subscribeWithDemo($user, proPrice());

    $subscription = app(CancelSubscription::class)->handle($subscription);

    expect($subscription->cancel_at_period_end)->toBeTrue()
        ->and($subscription->ends_at->equalTo($subscription->current_period_end))->toBeTrue()
        ->and($user->subscribed())->toBeTrue();

    $subscription = app(ResumeSubscription::class)->handle($subscription);
    expect($subscription->cancel_at_period_end)->toBeFalse()->and($subscription->ends_at)->toBeNull();

    $subscription = app(CancelSubscription::class)->handle($subscription);
    $this->travelTo($subscription->ends_at->addMinute());
    $this->artisan('payments:end-subscriptions')->assertSuccessful();

    expect($subscription->fresh())
        ->status->toBe(SubscriptionStatus::Canceled)
        ->active_user_id->toBeNull()
        ->and($user->subscribed())->toBeFalse();

    $this->artisan('payments:end-subscriptions')->assertSuccessful();
    Notification::assertSentToTimes($user, SubscriptionCanceled::class, 1);
});

test('a cancellation cannot be taken back once the period has run out', function () {
    $subscription = app(CancelSubscription::class)->handle(Payments::subscribeWithDemo(User::factory()->create(), proPrice()));

    $this->travelTo($subscription->ends_at->addMinute());

    app(ResumeSubscription::class)->handle($subscription);
})->throws(PaymentNotAllowed::class);

test('cancelling now ends access at once', function () {
    $user = User::factory()->create();
    $subscription = app(CancelSubscription::class)->handle(Payments::subscribeWithDemo($user, proPrice()), atPeriodEnd: false);

    expect($subscription->status)->toBe(SubscriptionStatus::Canceled)
        ->and($subscription->active_user_id)->toBeNull()
        ->and($user->subscribed())->toBeFalse();
});

test('a scheduled cancellation cut short by an immediate one ends access at once', function () {
    $user = User::factory()->create();
    $subscription = app(CancelSubscription::class)->handle(Payments::subscribeWithDemo($user, proPrice()));

    app(CancelSubscription::class)->handle($subscription, atPeriodEnd: false);

    expect($user->subscribed())->toBeFalse();
});

test('an ended user can subscribe again', function () {
    $user = User::factory()->create();
    $price = proPrice();
    app(CancelSubscription::class)->handle(Payments::subscribeWithDemo($user, $price), atPeriodEnd: false);

    expect(Payments::subscribeWithDemo($user, $price)->status)->toBe(SubscriptionStatus::Active);
});

test('changing plan moves the subscription to the new price', function () {
    $user = User::factory()->create();
    $subscription = Payments::subscribeWithDemo($user, proPrice());
    $team = PlanPrice::factory()->for(Plan::factory()->state(['key' => 'team']))->create(['amount' => 9900]);

    expect(app(SwapSubscriptionPlan::class)->handle($subscription, $team))->toBeNull();

    expect($subscription->fresh())
        ->plan_price_id->toBe($team->id)
        ->plan_id->toBe($team->plan_id)
        ->and($user->subscribed('team'))->toBeTrue()
        ->and($user->subscribed('pro'))->toBeFalse();
});

test('a plan cannot be changed to one in another currency, or while a cancellation is scheduled', function (Closure $arrange) {
    $subscription = Payments::subscribeWithDemo(User::factory()->create(), proPrice());
    [$subscription, $price] = $arrange($subscription);

    app(SwapSubscriptionPlan::class)->handle($subscription, $price);
})->with([
    'another currency' => fn (Subscription $subscription): array => [$subscription, PlanPrice::factory()->create(['currency' => Currency::USD])],
    'the same price' => fn (Subscription $subscription): array => [$subscription, $subscription->price],
    'a retired price' => fn (Subscription $subscription): array => [$subscription, PlanPrice::factory()->create(['is_active' => false])],
    'cancellation scheduled' => fn (Subscription $subscription): array => [app(CancelSubscription::class)->handle($subscription), PlanPrice::factory()->create()],
])->throws(PaymentNotAllowed::class);

test('a user with a running subscription cannot start a second', function () {
    $user = User::factory()->create();
    $price = proPrice();
    Payments::subscribeWithDemo($user, $price);

    Payments::subscribe($user, $price);
})->throws(PaymentNotAllowed::class, 'You already have a subscription');

test('an unfinished checkout does not block a new attempt, and is expired', function () {
    $user = User::factory()->create();
    $price = proPrice();
    $abandoned = Payments::subscribe($user, $price);

    $retry = Payments::subscribe($user, $price);

    expect($abandoned->fresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($abandoned->fresh()->active_user_id)->toBeNull()
        ->and($retry->status)->toBe(SubscriptionStatus::Incomplete)
        ->and($retry->active_user_id)->toBe($user->id);
});

test('the same subscribe request twice opens one subscription', function () {
    $user = User::factory()->create();
    $price = proPrice();

    $first = Payments::subscribe($user, $price, key: 'subscribe:same');
    $second = Payments::subscribe($user, $price, key: 'subscribe:same');

    expect($second->is($first))->toBeTrue()
        ->and(Subscription::count())->toBe(1);
});

test('the database refuses a second live subscription for one user', function () {
    $user = User::factory()->create();
    Subscription::factory()->for($user)->create();

    Subscription::factory()->for($user)->create();
})->throws(UniqueConstraintViolationException::class);

test('subscribing is refused when it is not on offer', function (Closure $arrange) {
    $user = User::factory()->create();
    $price = proPrice();
    [$user, $price, $gateway] = $arrange($user, $price);

    Payments::subscribe($user, $price, $gateway);
})->with([
    'payments switched off' => function (User $user, PlanPrice $price): array {
        Payments::enable(['payments_enabled' => false]);

        return [$user, $price, Gateway::Demo];
    },
    'a gateway not offered' => fn (User $user, PlanPrice $price): array => [$user, $price, Gateway::Stripe],
    'manual payments' => fn (User $user, PlanPrice $price): array => [$user, $price, Gateway::Manual],
    'a retired price' => fn (User $user, PlanPrice $price): array => [$user, tap($price)->update(['is_active' => false]), Gateway::Demo],
    'a plan off offer' => function (User $user, PlanPrice $price): array {
        $price->plan->update(['is_active' => false]);

        return [$user, $price->fresh(), Gateway::Demo];
    },
    'a price in another currency' => fn (User $user, PlanPrice $price): array => [$user, PlanPrice::factory()->create(['currency' => Currency::USD]), Gateway::Demo],
    'an unverified user' => fn (User $user, PlanPrice $price): array => [User::factory()->unverified()->create(), $price, Gateway::Demo],
])->throws(PaymentNotAllowed::class);

test('a late event cannot bring an ended subscription back', function () {
    $user = User::factory()->create();
    $subscription = app(CancelSubscription::class)->handle(Payments::subscribeWithDemo($user, proPrice()), atPeriodEnd: false);

    $subscription = app(ReconcileSubscription::class)->apply($subscription, new GatewaySubscriptionState(SubscriptionStatus::Active), TransactionSource::Webhook);

    expect($subscription->status)->toBe(SubscriptionStatus::Canceled)
        ->and($user->subscribed())->toBeFalse();
});

test('the subscriber\'s payments name the subscription and never double count', function () {
    $subscription = renew(Payments::subscribeWithDemo(User::factory()->create(), proPrice()));

    expect(Payment::query()->where('payable_type', $subscription->getMorphClass())->where('payable_id', $subscription->id)->count())->toBe(2)
        ->and(Payment::query()->pluck('gateway_payment_id')->unique()->count())->toBe(2);
});
