<?php

use App\Livewire\Shop\Basket;
use App\Models\Order;
use App\Models\Package;
use App\Notifications\OrderConfirmed;
use App\Shop\Cart;
use App\Shop\Checkout;
use App\Shop\OrderStatus;
use App\Shop\PackageUnavailableException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * Put packages in the basket and fill the checkout form.
 *
 * @param  list<Package>  $packages
 */
function checkoutWith(array $packages): Testable
{
    foreach ($packages as $package) {
        app(Cart::class)->add($package);
    }

    return Livewire::test(Basket::class)
        ->set('name', 'Maya Okafor')
        ->set('email', 'maya@example.test')
        ->set('acceptTerms', true);
}

test('the basket lists what is in it with the total', function () {
    $reef = Package::factory()->active()->create(['title' => 'Reef', 'price_cents' => 29900]);
    $bled = Package::factory()->active()->create(['title' => 'Bled', 'price_cents' => 19900]);
    app(Cart::class)->add($reef);
    app(Cart::class)->add($bled);

    $this->get(route('cart'))
        ->assertSuccessful()
        ->assertSeeInOrder(['Reef', 'Bled'])
        ->assertSee('$498.00');
});

test('a package can be removed from the basket', function () {
    $package = Package::factory()->active()->create(['title' => 'Removable Reef']);
    app(Cart::class)->add($package);

    Livewire::test(Basket::class)
        ->call('remove', $package->id)
        ->assertDispatched('cart-updated')
        ->assertDontSee('Removable Reef')
        ->assertSee('Your basket is empty');
});

test('placing an order records it and takes limited licences out of stock', function () {
    $limited = Package::factory()->active()->limited(3)->create(['title' => 'Volcano', 'price_cents' => 59900]);
    $unlimited = Package::factory()->active()->create(['title' => 'Bled', 'price_cents' => 19900]);

    $component = checkoutWith([$limited, $unlimited])->call('placeOrder');

    $order = Order::sole();

    $component->assertRedirect(URL::signedRoute('orders.show', $order));

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->customer_email)->toBe('maya@example.test')
        ->and($order->total_cents)->toBe(79800)
        ->and($order->items->pluck('package_title')->all())->toEqualCanonicalizing(['Volcano', 'Bled'])
        ->and($limited->fresh()->stock)->toBe(2)
        ->and($unlimited->fresh()->stock)->toBeNull()
        ->and(app(Cart::class)->count())->toBe(0);
});

test('placing an order emails the customer a confirmation', function () {
    checkoutWith([Package::factory()->active()->create()])->call('placeOrder');

    Notification::assertSentTo(
        new AnonymousNotifiable,
        OrderConfirmed::class,
        fn (OrderConfirmed $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'maya@example.test',
    );
});

test('a package that sold out after it was added is removed and nothing is charged', function () {
    $package = Package::factory()->active()->limited(1)->create(['title' => 'Last Volcano Licence']);

    $component = checkoutWith([$package]);

    $package->update(['stock' => 0]);

    $component->call('placeOrder')
        ->assertHasErrors('basket')
        ->assertNoRedirect();

    expect(Order::count())->toBe(0)
        ->and(app(Cart::class)->has($package->id))->toBeFalse();
});

test('checkout requires a name, an email and accepted terms', function () {
    checkoutWith([Package::factory()->active()->create()])
        ->set('name', '')
        ->set('email', 'not-an-email')
        ->set('acceptTerms', false)
        ->call('placeOrder')
        ->assertHasErrors(['name' => 'required', 'email' => 'email', 'acceptTerms' => 'accepted']);

    expect(Order::count())->toBe(0);
});

test('an empty basket cannot be checked out', function () {
    checkoutWith([])
        ->call('placeOrder')
        ->assertHasErrors('basket');

    expect(Order::count())->toBe(0);
});

test('the order confirmation needs a valid signature', function () {
    $order = Order::factory()->create(['customer_name' => 'Maya Okafor']);

    $this->get(route('orders.show', $order))->assertForbidden();

    $this->get(URL::signedRoute('orders.show', $order))
        ->assertSuccessful()
        ->assertSee('Thank you, Maya Okafor!')
        ->assertSee($order->reference);
});

test('two checkouts cannot both take the last licence', function () {
    $package = Package::factory()->active()->limited(1)->create();
    $checkout = app(Checkout::class);

    $checkout->placeOrder([$package->id], 'First Buyer', 'first@example.test');

    expect(fn () => $checkout->placeOrder([$package->id], 'Second Buyer', 'second@example.test'))
        ->toThrow(PackageUnavailableException::class);

    expect(Order::count())->toBe(1)
        ->and($package->fresh()->stock)->toBe(0);
});

test('refunding an order returns its licences to stock exactly once', function () {
    $package = Package::factory()->active()->limited(2)->create();
    $checkout = app(Checkout::class);
    $order = $checkout->placeOrder([$package->id], 'Maya Okafor', 'maya@example.test');

    $checkout->refund($order);
    $checkout->refund($order);

    expect($order->status)->toBe(OrderStatus::Refunded)
        ->and($package->fresh()->stock)->toBe(2);
});

test('the confirmation email escapes markdown in the customer name', function () {
    $order = Order::factory()->create(['customer_name' => '[Claim refund](https://evil.example)']);

    $mail = (new OrderConfirmed($order->load('items')))->toMail(new AnonymousNotifiable);

    expect($mail->greeting)->toContain('\[Claim refund\]');
});
