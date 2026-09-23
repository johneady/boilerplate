<?php

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\Shop\Checkout;
use App\Shop\OrderStatus;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

test('an administrator sees the orders', function () {
    $orders = Order::factory()->count(2)->create();

    $this->actingAs($this->admin);

    Livewire::test(ManageOrders::class)->assertCanSeeTableRecords($orders);
});

test('an ordinary user cannot reach the orders screen', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/orders')
        ->assertForbidden();
});

test('the orders screen offers no way to create an order', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManageOrders::class)->assertActionDoesNotExist('create');
});

test('an administrator can mark an order fulfilled', function () {
    $order = Order::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(ManageOrders::class)->callTableAction('fulfil', $order);

    expect($order->fresh()->status)->toBe(OrderStatus::Fulfilled)
        ->and($order->fresh()->fulfilled_at)->not->toBeNull();
});

test('refunding from the panel puts the licences back into stock', function () {
    $package = Package::factory()->active()->limited(1)->create();
    $order = app(Checkout::class)->placeOrder([$package->id], 'Maya Okafor', 'maya@example.test');

    $this->actingAs($this->admin);

    Livewire::test(ManageOrders::class)->callTableAction('refund', $order);

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($package->fresh()->stock)->toBe(1);
});
