<?php

use App\Livewire\Ordering\Checkout;
use App\Livewire\Ordering\Menu;
use App\Models\Order;
use App\Models\Product;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Livewire\Livewire;

beforeEach(function () {
    app(Settings::class)->set(SettingKey::Timezone, 'America/Toronto');
    // 10:00 in Toronto: the café is open, so today's slots start at 10:30.
    $this->travelTo(now('America/Toronto')->setTime(10, 0)->utc());
});

test('the menu lists available products and adds them to the cart', function () {
    $croissant = Product::factory()->create(['name' => 'All-Butter Croissant']);
    Product::factory()->unavailable()->create(['name' => 'Sold Out Scone']);

    Livewire::test(Menu::class)
        ->assertSee('All-Butter Croissant')
        ->assertDontSee('Sold Out Scone')
        ->call('addToCart', $croissant->id)
        ->assertDispatched('cart-updated');

    $this->get(route('checkout'))->assertSee('All-Butter Croissant');
});

test('a customer places a pickup order and is sent to its signed order page', function () {
    $product = Product::factory()->create(['price_cents' => 425]);
    Livewire::test(Menu::class)->call('addToCart', $product->id)->call('addToCart', $product->id);

    $component = Livewire::test(Checkout::class)
        ->assertSet('readyAt', now('America/Toronto')->format('Y-m-d').' 10:30')
        ->set('name', 'Jordan Lee')
        ->set('email', 'jordan@example.com')
        ->set('phone', '(416) 555-0199')
        ->call('placeOrder')
        ->assertHasNoErrors();

    $order = Order::query()->sole();

    $component->assertRedirect($order->trackingUrl());

    expect($order->total_cents)->toBe(850)
        ->and($order->ready_at->setTimezone('America/Toronto')->format('H:i'))->toBe('10:30');
});

test('checkout validates the address for delivery and only accepts offered times', function () {
    $product = Product::factory()->create();
    Livewire::test(Menu::class)->call('addToCart', $product->id);

    Livewire::test(Checkout::class)
        ->set('name', 'Jordan Lee')
        ->set('email', 'jordan@example.com')
        ->set('phone', '(416) 555-0199')
        ->set('fulfilment', 'delivery')
        ->call('placeOrder')
        ->assertHasErrors(['address' => 'required_if'])
        ->set('address', '88 Carlaw Ave')
        // Before opening, and sooner than the kitchen can manage.
        ->set('readyAt', now('America/Toronto')->format('Y-m-d').' 06:00')
        ->call('placeOrder')
        ->assertHasErrors('readyAt');

    expect(Order::query()->count())->toBe(0);
});

test('the order page needs its signature', function () {
    $order = Order::factory()->create();

    $this->get(route('orders.show', $order))->assertForbidden();
    $this->get($order->trackingUrl())->assertSuccessful()->assertSee($order->number);
});
