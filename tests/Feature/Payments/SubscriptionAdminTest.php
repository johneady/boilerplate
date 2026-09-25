<?php

use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Filament\Resources\Plans\RelationManagers\PricesRelationManager;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Resources\Subscriptions\Pages\ViewSubscription;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Payments\Enums\BillingInterval;
use App\Payments\Enums\Currency;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Exceptions\ImmutableRecordException;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
});

test('the subscription screens are reachable by an administrator', function (string $path) {
    $this->actingAs($this->admin)->get($path)->assertSuccessful();
})->with(['/admin/plans', '/admin/plans/create', '/admin/subscriptions']);

test('the subscription screens are closed to ordinary users', function (string $path) {
    $this->actingAs(User::factory()->create())->get($path)->assertForbidden();
})->with(['/admin/plans', '/admin/subscriptions']);

test('the subscription screens disappear while payments are switched off', function (string $path) {
    Payments::enable(['payments_enabled' => false]);

    $this->actingAs($this->admin)->get($path)->assertForbidden();
})->with(['/admin/plans', '/admin/subscriptions']);

test('an administrator creates a plan and lands on it to add prices', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreatePlan::class)
        ->fillForm(['name' => 'Pro', 'key' => 'pro', 'features' => ['Unlimited projects'], 'trial_days' => 14])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    expect(Plan::sole())
        ->key->toBe('pro')
        ->trial_days->toBe(14)
        ->features->toBe(['Unlimited projects']);
});

test('a plan key must be unique and fit for code', function (string $key) {
    Plan::factory()->create(['key' => 'pro']);
    $this->actingAs($this->admin);

    Livewire::test(CreatePlan::class)
        ->fillForm(['name' => 'Pro', 'key' => $key, 'trial_days' => 0])
        ->call('create')
        ->assertHasFormErrors(['key']);
})->with(['pro', 'not a key']);

test('a price is added in the installation\'s currency and synced to the gateways', function () {
    $plan = Plan::factory()->create();
    $this->actingAs($this->admin);

    Livewire::test(PricesRelationManager::class, ['ownerRecord' => $plan, 'pageClass' => EditPlan::class])
        ->callTableAction(CreateAction::class, data: ['amount' => '290.00', 'interval' => BillingInterval::Year->value, 'interval_count' => 1])
        ->assertHasNoTableActionErrors();

    expect($plan->prices()->sole())
        ->amount->toBe(29000)
        ->currency->toBe(Currency::CAD)
        ->interval->toBe(BillingInterval::Year)
        // The Demo gateway is on, so the price is known to it at once.
        ->gatewayRef(Gateway::Demo, GatewayMode::Sandbox)->not->toBeNull();
});

test('a price is retired rather than edited or deleted', function () {
    $price = PlanPrice::factory()->create();
    $this->actingAs($this->admin);

    Livewire::test(PricesRelationManager::class, ['ownerRecord' => $price->plan, 'pageClass' => EditPlan::class])
        ->callTableAction('deactivate', $price)
        ->assertTableActionHidden('deactivate', $price->fresh())
        ->assertTableActionVisible('activate', $price->fresh());

    expect($price->fresh()->is_active)->toBeFalse()
        ->and(fn () => $price->update(['amount' => 100]))->toThrow(ImmutableRecordException::class)
        ->and(fn () => $price->delete())->toThrow(ImmutableRecordException::class);
});

test('nobody may delete a plan or a subscription, administrators included', function () {
    $subscription = Subscription::factory()->create();

    expect($this->admin->can('delete', $subscription->plan))->toBeFalse()
        ->and($this->admin->can('delete', $subscription->price))->toBeFalse()
        ->and($this->admin->can('delete', $subscription))->toBeFalse()
        ->and($this->admin->can('update', $subscription))->toBeFalse()
        ->and(fn () => $subscription->delete())->toThrow(ImmutableRecordException::class);
});

test('the plans list shows whether each plan is synced', function () {
    $plan = PlanPrice::factory()->create()->plan;
    $this->actingAs($this->admin);

    Livewire::test(ListPlans::class)
        ->assertCanSeeTableRecords([$plan])
        ->assertSee(__('payments.plans.gateway_synced', ['gateway' => 'Demo']));
});

test('the subscriptions list shows the current mode by default', function () {
    $sandbox = Subscription::factory()->create();
    $live = Subscription::factory()->create(['mode' => GatewayMode::Live]);
    $this->actingAs($this->admin);

    Livewire::test(ListSubscriptions::class)
        ->assertCanSeeTableRecords([$sandbox])
        ->assertCanNotSeeTableRecords([$live]);
});

test('an administrator can simulate a demo renewal and a failed renewal', function () {
    $subscription = Payments::subscribeWithDemo(User::factory()->create(), PlanPrice::factory()->create());
    $this->actingAs($this->admin);

    Livewire::test(ViewSubscription::class, ['record' => $subscription->uuid])
        ->callAction('simulateRenewal')
        ->assertNotified(__('payments.subscriptions.renewed'));

    expect($subscription->payments()->count())->toBe(2);

    Livewire::test(ViewSubscription::class, ['record' => $subscription->uuid])
        ->callAction('simulateFailedRenewal');

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue);
});

test('an administrator can cancel at period end, resume, and cancel now', function () {
    $subscription = Payments::subscribeWithDemo(User::factory()->create(), PlanPrice::factory()->create());
    $this->actingAs($this->admin);
    $page = fn () => Livewire::test(ViewSubscription::class, ['record' => $subscription->uuid]);

    $page()->callAction('cancelAtPeriodEnd');
    expect($subscription->fresh()->cancel_at_period_end)->toBeTrue();

    $page()->assertActionHidden('simulateRenewal')->callAction('resume');
    expect($subscription->fresh()->cancel_at_period_end)->toBeFalse();

    $page()->callAction('cancelNow');
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Canceled);

    $page()->assertActionHidden('cancelNow')->assertActionHidden('resume');
});

test('an ordinary user cannot change a subscription', function () {
    $subscription = Subscription::factory()->create();

    expect(User::factory()->create()->can('manage', $subscription))->toBeFalse()
        ->and($this->admin->can('manage', $subscription))->toBeTrue();
});
