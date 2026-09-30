<?php

use App\Auth\Role;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Order;
use App\Models\User;
use App\Ordering\OrderStatus;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

test('orders move forward one step at a time and stop when finished', function () {
    $order = Order::factory()->create();

    foreach ([OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed, OrderStatus::Completed] as $expected) {
        $order->advance();
        expect($order->refresh()->status)->toBe($expected);
    }

    $order->cancel();
    expect($order->refresh()->status)->toBe(OrderStatus::Completed);
});

test('staff advance and cancel orders from the panel', function () {
    $this->actingAs(User::factory()->role(Role::Manager)->create());
    $order = Order::factory()->create();

    Livewire::test(ListOrders::class)
        ->assertCanSeeTableRecords([$order])
        ->callAction(TestAction::make('advance')->table($order));

    expect($order->refresh()->status)->toBe(OrderStatus::Preparing);

    Livewire::test(ViewOrder::class, ['record' => $order->number])
        ->callAction('cancel');

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
});

test('a bookkeeper can read orders but not change them or the menu', function () {
    $this->actingAs(User::factory()->role(Role::Bookkeeper)->create());
    $order = Order::factory()->create();

    Livewire::test(ListOrders::class)
        ->assertCanSeeTableRecords([$order])
        ->assertActionHidden(TestAction::make('advance')->table($order));

    $this->get(ManageProducts::getUrl())->assertForbidden();
});
