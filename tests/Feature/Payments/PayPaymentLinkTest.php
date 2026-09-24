<?php

use App\Livewire\Payments\PayPaymentLink;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\TaxRate;
use App\Payments\Enums\PaymentStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Support\PaymentFixtures;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
});

test('the pay page shows the price with its taxes', function () {
    TaxRate::factory()->rate('GST', '5')->create();
    TaxRate::factory()->rate('PST', '7')->create(['sort_order' => 1]);
    $link = PaymentLink::factory()->costing(10000)->taxable()->create(['title' => 'Logo design']);

    $this->get($link->url())
        ->assertOk()
        ->assertSee('Logo design')
        ->assertSeeInOrder(['GST (5%)', 'CA$5.00', 'PST (7%)', 'CA$7.00', 'CA$112.00']);
});

test('paying sends the customer to the gateway with a payment priced by the link', function () {
    $link = PaymentLink::factory()->costing(2500)->create();

    Livewire::test(PayPaymentLink::class, ['paymentLink' => $link])
        ->set('name', 'Sam Customer')
        ->set('email', 'sam@example.test')
        ->set('gateway', 'demo')
        ->call('pay')
        ->assertHasNoErrors()
        ->assertRedirect(route('payments.demo.show', Payment::sole()));

    expect(Payment::sole())
        ->amount->toBe(2500)
        ->customer_email->toBe('sam@example.test');
});

test('pressing pay twice opens one checkout, not two', function () {
    $component = Livewire::test(PayPaymentLink::class, ['paymentLink' => PaymentLink::factory()->create()])
        ->set('name', 'Sam Customer')
        ->set('email', 'sam@example.test')
        ->set('gateway', 'demo');

    $component->call('pay');
    $component->call('pay');

    expect(Payment::count())->toBe(1);
});

test('a customer-entered amount is charged as entered', function () {
    $link = PaymentLink::factory()->customerEntered(min: 500, max: 50000)->create();

    Livewire::test(PayPaymentLink::class, ['paymentLink' => $link])
        ->set('amount', '42.50')
        ->set('name', 'Sam Customer')
        ->set('email', 'sam@example.test')
        ->set('gateway', 'demo')
        ->call('pay')
        ->assertHasNoErrors();

    expect(Payment::sole()->amount)->toBe(4250);
});

test('a customer-entered amount outside the link\'s bounds is refused', function (string $amount) {
    $link = PaymentLink::factory()->customerEntered(min: 500, max: 50000)->create();

    Livewire::test(PayPaymentLink::class, ['paymentLink' => $link])
        ->set('amount', $amount)
        ->set('name', 'Sam Customer')
        ->set('email', 'sam@example.test')
        ->set('gateway', 'demo')
        ->call('pay')
        ->assertHasErrors('amount');

    expect(Payment::count())->toBe(0);
})->with([
    'below the minimum' => '4.99',
    'above the maximum' => '500.01',
    'not an amount' => 'twenty',
    'fractions of a cent' => '10.001',
    'blank' => '',
]);

test('after a checkout fails to open, pressing pay again tries afresh', function () {
    Payments::enable(['stripe_enabled' => true, 'stripe_sandbox_secret_key' => 'sk_test_51Example']);
    Http::preventStrayRequests();
    Http::fakeSequence('api.stripe.com/v1/checkout/sessions')
        ->push(['error' => ['message' => 'Stripe is having a moment']], 500)
        ->push(PaymentFixtures::load('stripe/checkout_session_open'));

    $component = Livewire::test(PayPaymentLink::class, ['paymentLink' => PaymentLink::factory()->create()])
        ->set('name', 'Sam Customer')
        ->set('email', 'sam@example.test')
        ->set('gateway', 'stripe');

    $component->call('pay')->assertHasErrors('gateway');
    $component->call('pay')->assertHasNoErrors()->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_a1B2c3');

    expect(Payment::query()->orderBy('id')->pluck('status')->all())->toBe([PaymentStatus::Failed, PaymentStatus::Pending]);
});

test('a gateway that is not offered cannot be chosen', function () {
    Livewire::test(PayPaymentLink::class, ['paymentLink' => PaymentLink::factory()->create()])
        ->set('name', 'Sam Customer')
        ->set('email', 'sam@example.test')
        ->set('gateway', 'stripe')
        ->call('pay')
        ->assertHasErrors('gateway');
});

test('a closed link shows why instead of a form', function () {
    $link = PaymentLink::factory()->singleUse()->create();
    Payments::payWithDemo($link);

    $this->get($link->url())->assertOk()->assertSee(__('This payment link is no longer accepting payments.'))->assertDontSee(__('Continue to payment'));
});

test('an inactive link answers 404', function () {
    $this->get(PaymentLink::factory()->inactive()->create()->url())->assertNotFound();
});

test('with no gateway offered the page says so rather than offering a form', function () {
    Payments::enable(['demo_gateway_enabled' => false]);

    $this->get(PaymentLink::factory()->create()->url())->assertOk()->assertSee(__('Online payment is not available at the moment. Please contact us to pay.'));
});

test('checkout attempts are rate limited per address', function () {
    $link = PaymentLink::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        RateLimiter::increment('payment-checkout:127.0.0.1', 3600);
    }

    Livewire::test(PayPaymentLink::class, ['paymentLink' => $link])
        ->set('name', 'Sam Customer')
        ->set('email', 'sam@example.test')
        ->set('gateway', 'demo')
        ->call('pay')
        ->assertHasErrors('gateway');

    expect(Payment::count())->toBe(0);
});
