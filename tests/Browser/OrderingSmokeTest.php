<?php

use App\Models\Order;
use App\Models\Product;
use App\Ordering\OrderStatus;

/*
 * The customer's whole path in a real browser: add from the menu, check out
 * as a guest, and land on the signed order page. The feature suite covers the
 * server side of each step; this catches a button that renders but does
 * nothing when clicked.
 */

test('a guest orders from the menu and lands on their order page', function () {
    Product::factory()->create(['name' => 'All-Butter Croissant', 'price_cents' => 425]);

    $page = visit('/');

    $page->assertSee('All-Butter Croissant')
        ->click('@add-to-cart')
        ->assertSeeIn('@cart-count', '1')
        ->click('@cart-button')
        ->assertSee('Your order')
        ->assertSeeIn('@order-total', '$4.25')
        ->fill('name', 'Jordan Lee')
        ->fill('phone', '(416) 555-0199')
        ->fill('email', 'jordan@example.com')
        ->click('@place-order')
        ->assertSee('Thanks, Jordan! Your order is in.')
        ->assertNoJavaScriptErrors();

    expect(Order::query()->sole())
        ->status->toBe(OrderStatus::New)
        ->total_cents->toBe(425);
});
