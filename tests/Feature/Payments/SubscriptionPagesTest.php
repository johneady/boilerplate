<?php

use App\Livewire\Payments\Pricing;
use App\Livewire\Settings\Billing;
use App\Models\Page;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Payments\Enums\Currency;
use App\Payments\Enums\SubscriptionStatus;
use App\Settings\Settings;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
    Notification::fake();
});

function pricedPlan(string $key = 'pro', int $amount = 2900): PlanPrice
{
    return PlanPrice::factory()->for(Plan::factory()->state(['key' => $key, 'name' => ucfirst($key)]))->create(['amount' => $amount]);
}

test('the pricing page lists the plans on offer in the installation\'s currency', function () {
    $pro = pricedPlan();
    PlanPrice::factory()->for(Plan::factory()->state(['name' => 'Hidden plan', 'is_active' => false]))->create();
    PlanPrice::factory()->for(Plan::factory()->state(['name' => 'US only']))->create(['currency' => Currency::USD]);

    $this->get(route('payments.pricing'))
        ->assertOk()
        ->assertSee('Pro')
        ->assertSee($pro->label())
        ->assertDontSee('Hidden plan')
        ->assertDontSee('US only');
});

test('the pricing page is not found while payments are switched off', function () {
    Payments::enable(['payments_enabled' => false]);

    $this->get(route('payments.pricing'))->assertNotFound();
});

test('pricing is a reserved page slug, so no content page can hide behind it', function () {
    expect(Page::RESERVED_SLUGS)->toContain('pricing')
        ->and(Route::has('payments.pricing'))->toBeTrue();
});

test('a guest who subscribes is asked to sign in first', function () {
    $price = pricedPlan();

    Livewire::test(Pricing::class)
        ->call('subscribe', $price->id)
        ->assertRedirect(route('login'));

    expect(Subscription::count())->toBe(0);
});

test('an unverified user is asked to verify their email first', function () {
    $price = pricedPlan();

    Livewire::actingAs(User::factory()->unverified()->create())
        ->test(Pricing::class)
        ->call('subscribe', $price->id)
        ->assertRedirect(route('verification.notice'));
});

test('a signed-in user is sent to the gateway\'s checkout', function () {
    $user = User::factory()->create();
    $price = pricedPlan();

    Livewire::actingAs($user)
        ->test(Pricing::class)
        ->call('subscribe', $price->id)
        ->assertRedirect(route('subscriptions.demo.show', Subscription::sole()));

    expect(Subscription::sole())
        ->user_id->toBe($user->id)
        ->plan_price_id->toBe($price->id)
        ->status->toBe(SubscriptionStatus::Incomplete);
});

test('a double-clicked subscribe opens one checkout', function () {
    $user = User::factory()->create();
    $price = pricedPlan();

    $page = Livewire::actingAs($user)->test(Pricing::class);
    $page->call('subscribe', $price->id);
    $page->call('subscribe', $price->id);

    expect(Subscription::count())->toBe(1);
});

test('a retired price cannot be subscribed to from a stale page', function () {
    $user = User::factory()->create();
    $price = pricedPlan();
    $page = Livewire::actingAs($user)->test(Pricing::class);

    $price->update(['is_active' => false]);

    $page->call('subscribe', $price->id)->assertHasErrors('subscribe');
    expect(Subscription::count())->toBe(0);
});

test('subscribe attempts are rate limited per account', function () {
    $user = User::factory()->create();
    $price = pricedPlan();

    foreach (range(1, 10) as $attempt) {
        Livewire::actingAs($user)->test(Pricing::class)->call('subscribe', $price->id);
    }

    Livewire::actingAs($user)->test(Pricing::class)
        ->call('subscribe', $price->id)
        ->assertHasErrors(['subscribe' => __('Too many attempts. Please try again later.')]);
});

test('the demo checkout, approved, returns the subscriber to their billing page subscribed', function () {
    $user = User::factory()->create();
    $subscription = Payments::subscribe($user, pricedPlan());

    $this->get(route('subscriptions.demo.show', $subscription))->assertOk()->assertSee('Pro');

    $return = $this->post(route('subscriptions.demo.store', $subscription), ['outcome' => 'approve']);
    $return->assertRedirect($subscription->returnUrl());

    $this->actingAs($user)->get($subscription->returnUrl())->assertRedirect(route('billing.edit'));

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($user->subscribed('pro'))->toBeTrue();
});

test('backing out of the demo checkout returns to the pricing page', function () {
    $subscription = Payments::subscribe(User::factory()->create(), pricedPlan());

    $this->post(route('subscriptions.demo.store', $subscription), ['outcome' => 'cancel'])
        ->assertRedirect($subscription->cancelUrl());

    $this->get($subscription->cancelUrl())
        ->assertRedirect(route('payments.pricing'))
        ->assertSessionHas('subscription_cancelled');
});

test('the subscription return and cancel links must be signed', function (string $route) {
    $subscription = Payments::subscribe(User::factory()->create(), pricedPlan());

    $this->get(route($route, $subscription))->assertForbidden();
})->with(['subscriptions.return', 'subscriptions.cancelled']);

test('the return link ignores the parameters gateways append', function () {
    $subscription = Payments::subscribe(User::factory()->create(), pricedPlan());

    $this->get($subscription->returnUrl().'&session_id=cs_test_Sub1&subscription_id=I-BW452GLLEP1G&ba_token=BA-1&token=T-1')
        ->assertRedirect(route('billing.edit'));
});

test('the billing page needs a verified sign-in', function () {
    $this->get(route('billing.edit'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->get(route('billing.edit'))->assertRedirect(route('verification.notice'));
});

test('the billing page shows the user\'s own subscription and payments only', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();
    $myPayment = Payments::subscribeWithDemo($mine, pricedPlan())->payments()->sole();
    $theirPayment = Payments::subscribeWithDemo($theirs, pricedPlan('team', 9900))->payments()->sole();

    $this->actingAs($mine)->get(route('billing.edit'))
        ->assertOk()
        ->assertSeeInOrder([__('Billing'), 'Pro'])
        ->assertSee($myPayment->receiptUrl(), escape: false)
        ->assertDontSee($theirPayment->uuid);

    $this->actingAs(User::factory()->create())->get(route('billing.edit'))
        ->assertSee(__('You do not have a subscription.'));
});

test('a subscriber can cancel at period end and change their mind', function () {
    $user = User::factory()->create();
    $subscription = Payments::subscribeWithDemo($user, pricedPlan());

    Livewire::actingAs($user)->test(Billing::class)
        ->call('cancel')
        ->assertSet('status', __('Your subscription will end when the current period does.'))
        ->assertSee(__('Keep my subscription'));

    expect($subscription->fresh()->cancel_at_period_end)->toBeTrue();

    Livewire::actingAs($user)->test(Billing::class)->call('resume');

    expect($subscription->fresh()->cancel_at_period_end)->toBeFalse();
});

test('a subscriber can change plan to another on offer', function () {
    $user = User::factory()->create();
    $subscription = Payments::subscribeWithDemo($user, pricedPlan());
    $team = pricedPlan('team', 9900);

    Livewire::actingAs($user)->test(Billing::class)
        ->set('newPriceId', (string) $team->id)
        ->call('swap')
        ->assertHasNoErrors()
        ->assertSet('status', __('Your plan has been changed.'));

    expect($subscription->fresh()->plan_price_id)->toBe($team->id);
});

test('a subscriber cannot change to a price that is not on offer to them', function () {
    $user = User::factory()->create();
    $subscription = Payments::subscribeWithDemo($user, pricedPlan());
    $usd = PlanPrice::factory()->create(['currency' => Currency::USD]);

    Livewire::actingAs($user)->test(Billing::class)
        ->set('newPriceId', (string) $usd->id)
        ->call('swap')
        ->assertHasErrors('newPriceId');

    expect($subscription->fresh()->plan_price_id)->not->toBe($usd->id);
});

test('a demo subscription has no payment method to change', function () {
    $user = User::factory()->create();
    Payments::subscribeWithDemo($user, pricedPlan());

    Livewire::actingAs($user)->test(Billing::class)
        ->call('updatePaymentMethod')
        ->assertHasErrors('billing');
});

test('the billing page warns a past-due subscriber how long they have', function () {
    $user = User::factory()->create();
    Subscription::factory()->for($user)->status(SubscriptionStatus::PastDue)->create(['past_due_since' => now()]);

    $this->actingAs($user)->get(route('billing.edit'))
        ->assertSee(__('Your last payment failed. Please update your payment method before :date to keep your subscription.', [
            'date' => app(Settings::class)->formatDate(now()->addDays(7)),
        ]));
});

test('the settings menu links to billing only while payments are on', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile.edit'))->assertSee(route('billing.edit'));

    Payments::enable(['payments_enabled' => false]);

    $this->actingAs($user)->get(route('profile.edit'))->assertDontSee(route('billing.edit'));
});

test('routes can require a subscription, to any plan or to one', function () {
    Route::middleware(['web', 'auth', 'subscribed'])->get('/_test/members', fn () => 'members');
    Route::middleware(['web', 'auth', 'subscribed:team'])->get('/_test/team', fn () => 'team');
    $user = User::factory()->create();

    $this->actingAs($user)->get('/_test/members')->assertRedirect(route('payments.pricing'));
    $this->actingAs($user)->getJson('/_test/members')->assertForbidden();

    Payments::subscribeWithDemo($user, pricedPlan());

    $this->actingAs($user)->get('/_test/members')->assertOk();
    $this->actingAs($user)->get('/_test/team')->assertRedirect(route('payments.pricing'));
});

test('a subscriber back from checkout before the gateway confirms sees it being set up', function () {
    $user = User::factory()->create();
    Payments::subscribe($user, pricedPlan());

    $this->actingAs($user)->withSession(['subscription_returned' => true])->get(route('billing.edit'))
        ->assertSee(__('Your subscription is being set up. This page will show it once the payment provider confirms it.'))
        ->assertDontSee(__('You do not have a subscription.'));
});
