<?php

use App\Auth\Role;
use App\Filament\Widgets\BusinessOverview;
use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\RevenueChart;
use App\Models\ContactSubmission;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Payments\Actions\RefundPayment;
use App\Payments\BusinessMetrics;
use App\Payments\Enums\BillingInterval;
use App\Payments\Enums\DisputeStatus;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
    $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00', 'UTC'));
});

test('net revenue is money in less money refunded, in the month each moved', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-20 12:00'));
    $august = Payments::payWithDemo(PaymentLink::factory()->costing(10000)->create());

    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00'));
    Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());
    app(RefundPayment::class)->handle($august, Money::of(2000, $august->currency), 'refund-key', null, User::factory()->admin()->create());
    $this->travel(1)->minute();

    $metrics = app(BusinessMetrics::class);

    // The refund lands in September, when the money went back, rather than
    // rewriting August.
    expect($metrics->netRevenue($metrics->monthStart(1), $metrics->monthStart())->amount)->toBe(10000)
        ->and($metrics->netRevenue($metrics->monthStart(), $metrics->now()->utc())->amount)->toBe(3000)
        ->and($metrics->grossRevenue($metrics->monthStart(), $metrics->now()->utc())->amount)->toBe(5000)
        ->and($metrics->refunded($metrics->monthStart(), $metrics->now()->utc())->amount)->toBe(2000);
});

test('live figures never include sandbox payments', function () {
    Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());

    Payments::enable(['payments_mode' => GatewayMode::Live->value]);
    $metrics = app(BusinessMetrics::class);

    expect($metrics->netRevenue($metrics->monthStart(), $metrics->now()->utc())->isZero())->toBeTrue();
});

test('monthly recurring revenue counts a yearly price as a twelfth and leaves trials out', function () {
    $monthly = PlanPrice::factory()->create(['amount' => 4900, 'interval' => BillingInterval::Month]);
    $yearly = PlanPrice::factory()->create(['amount' => 120000, 'interval' => BillingInterval::Year]);

    Subscription::factory()->create(['plan_price_id' => $monthly->id, 'plan_id' => $monthly->plan_id, 'status' => SubscriptionStatus::Active]);
    Subscription::factory()->create(['plan_price_id' => $yearly->id, 'plan_id' => $yearly->plan_id, 'status' => SubscriptionStatus::PastDue]);
    Subscription::factory()->create(['plan_price_id' => $monthly->id, 'plan_id' => $monthly->plan_id, 'status' => SubscriptionStatus::Trialing]);

    $metrics = app(BusinessMetrics::class);

    expect($metrics->monthlyRecurringRevenue()->amount)->toBe(4900 + 10000)
        ->and($metrics->activeSubscriptions())->toBe(3);
});

test('new customers are customer accounts, not staff', function () {
    User::factory()->count(2)->create();
    User::factory()->role(Role::Editor)->create();
    $this->travel(1)->minute();

    $metrics = app(BusinessMetrics::class);

    expect($metrics->newCustomers($metrics->monthStart(), $metrics->now()->utc()))->toBe(2);
});

test('late in a long month, last month so far stops at the end of last month', function () {
    // October 31st: last month (September) has only 30 days, so "the same
    // point last month" would otherwise run into October 1st.
    $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00'));
    Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());
    $this->travelTo(CarbonImmutable::parse('2026-10-31 20:00'));
    Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());
    $this->travel(1)->minute();

    $this->actingAs(User::factory()->admin()->create());

    // Both sales are October's; none belongs to the September baseline.
    Livewire::test(BusinessOverview::class)->assertSee('Nothing to compare with last month yet');
});

test('the overview shows this month\'s real figures', function () {
    Payments::payWithDemo(PaymentLink::factory()->costing(123400)->create());
    $this->travel(1)->minute();

    $this->actingAs(User::factory()->role(Role::Bookkeeper)->create());

    Livewire::test(BusinessOverview::class)
        ->assertSee('Revenue this month')
        ->assertSee('CA$1,234.00');
});

test('a second dashboard load within the TTL reads no ledger at all', function () {
    Payments::payWithDemo(PaymentLink::factory()->costing(10000)->create());
    $this->travel(1)->minute();

    $this->actingAs(User::factory()->role(Role::Bookkeeper)->create());

    Livewire::test(BusinessOverview::class)->assertSee('CA$100.00');

    $ledgerQueries = 0;
    DB::listen(function ($query) use (&$ledgerQueries): void {
        if (str_contains($query->sql, 'payment_transactions')) {
            $ledgerQueries++;
        }
    });

    Livewire::test(BusinessOverview::class)->assertSee('CA$100.00');

    // The figures came from the widget cache; the summary email reads
    // BusinessMetrics directly and stays uncached.
    expect($ledgerQueries)->toBe(0);
});

test('money widgets are hidden from staff who cannot see payments, and while payments are off', function () {
    $this->actingAs(User::factory()->role(Role::Editor)->create());

    expect(BusinessOverview::canView())->toBeFalse()
        ->and(RevenueChart::canView())->toBeFalse();

    $this->actingAs(User::factory()->role(Role::Bookkeeper)->create());
    Payments::enable(['payments_enabled' => false]);

    expect(BusinessOverview::canView())->toBeFalse()
        ->and(RevenueChart::canView())->toBeFalse();
});

test('the revenue chart covers the last twelve months', function () {
    $this->actingAs(User::factory()->admin()->create());

    $data = (fn () => $this->getData())->call(Livewire::test(RevenueChart::class)->instance());

    expect($data['labels'])->toHaveCount(12)
        ->and(end($data['labels']))->toBe('Sep 2026');
});

test('each item needing attention is shown only to someone who can act on it', function () {
    $payment = Payment::factory()->paid()->create();
    Dispute::factory()->for($payment)->create(['status' => DisputeStatus::NeedsResponse, 'mode' => $payment->mode]);
    ContactSubmission::factory()->create(['handled_at' => null]);

    $this->actingAs(User::factory()->role(Role::Bookkeeper)->create());

    Livewire::test(NeedsAttention::class)
        ->assertSee('1 dispute needs a response')
        // The status filter takes several values, so the link must too, or
        // it opens the whole unfiltered list.
        ->assertSee('filters%5Bstatus%5D%5Bvalues%5D%5B0%5D=needs_response', escape: false)
        ->assertDontSee('contact message');

    $this->actingAs(User::factory()->role(Role::Editor)->create());

    Livewire::test(NeedsAttention::class)
        ->assertSee('1 contact message is unanswered')
        ->assertDontSee('dispute');
});

test('with nothing outstanding the attention list says so', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(NeedsAttention::class)->assertSee('Nothing needs your attention right now.');
});
