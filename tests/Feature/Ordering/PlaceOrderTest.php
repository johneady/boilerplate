<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Ordering\Cart;
use App\Ordering\EmptyCart;
use App\Ordering\Fulfilment;
use App\Ordering\OrderStatus;
use App\Ordering\PlaceOrder;
use Carbon\CarbonImmutable;

/**
 * @return array{name: string, email: string, phone: string, fulfilment: Fulfilment, address: ?string, ready_at: CarbonImmutable, notes: ?string}
 */
function orderDetails(Fulfilment $fulfilment = Fulfilment::Pickup): array
{
    return [
        'name' => 'Jordan Lee',
        'email' => 'jordan@example.com',
        'phone' => '(416) 555-0199',
        'fulfilment' => $fulfilment,
        'address' => $fulfilment === Fulfilment::Delivery ? '88 Carlaw Ave' : null,
        'ready_at' => CarbonImmutable::now()->addHour(),
        'notes' => null,
    ];
}

test('an order is priced from the database and copies each line', function () {
    $croissant = Product::factory()->create(['name' => 'Croissant', 'price_cents' => 425]);
    $cake = Product::factory()->create(['name' => 'Cake', 'price_cents' => 4800]);

    $cart = app(Cart::class);
    $cart->add($croissant->id, 2);
    $cart->add($cake->id);

    $order = app(PlaceOrder::class)->handle(orderDetails());

    expect($order->status)->toBe(OrderStatus::New)
        ->and($order->number)->toBe(Order::numberFor($order->id))
        ->and($order->subtotal_cents)->toBe(5650)
        ->and($order->total_cents)->toBe(5650)
        ->and($order->items)->toHaveCount(2);

    // A later price change does not rewrite the order.
    $croissant->update(['price_cents' => 999]);

    expect($order->items()->where('product_name', 'Croissant')->sole())
        ->unit_price_cents->toBe(425)
        ->quantity->toBe(2)
        ->line_total_cents->toBe(850);

    expect($cart->isEmpty())->toBeTrue();
});

test('delivery is charged under the free delivery threshold and free above it', function (int $price, int $fee) {
    $product = Product::factory()->create(['price_cents' => $price]);
    app(Cart::class)->add($product->id);

    $order = app(PlaceOrder::class)->handle(orderDetails(Fulfilment::Delivery));

    expect($order->delivery_fee_cents)->toBe($fee)
        ->and($order->total_cents)->toBe($price + $fee)
        ->and($order->delivery_address)->toBe('88 Carlaw Ave');
})->with([
    'small order' => [1500, 500],
    'at the threshold' => [4000, 0],
]);

test('products switched off since they were added are left out of the order', function () {
    $available = Product::factory()->create(['price_cents' => 500]);
    $soldOut = Product::factory()->create(['price_cents' => 700]);

    $cart = app(Cart::class);
    $cart->add($available->id);
    $cart->add($soldOut->id);
    $soldOut->update(['is_available' => false]);

    $order = app(PlaceOrder::class)->handle(orderDetails());

    expect($order->items)->toHaveCount(1)
        ->and($order->total_cents)->toBe(500);
});

test('an empty cart cannot be ordered', function () {
    app(PlaceOrder::class)->handle(orderDetails());
})->throws(EmptyCart::class);

test('a signed in customer\'s order is linked to their account', function () {
    $user = User::factory()->create();
    app(Cart::class)->add(Product::factory()->create()->id);

    $order = app(PlaceOrder::class)->handle(orderDetails(), $user);

    expect($user->orders()->sole()->is($order))->toBeTrue();
});

test('the cart caps the quantity of one product', function () {
    $product = Product::factory()->create();
    $cart = app(Cart::class);

    $cart->add($product->id, Cart::MAX_QUANTITY + 5);

    expect($cart->count())->toBe(Cart::MAX_QUANTITY);
});
