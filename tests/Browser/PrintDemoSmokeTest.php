<?php

use App\Auth\Role;
use App\Models\PrintOrder;
use App\Models\User;
use App\Prints\Enums\PrintChannel;
use App\Prints\Enums\PrintOrderStatus;

/*
 * Browser coverage for the demo's two headline screens: the branded phone
 * flow a customer reaches from the home page, and the fulfillment console a
 * staff member lands on after the one-click login. File uploads cannot be
 * driven by the plugin (see .ai/rules/browser.md) and are covered by the
 * feature suite; what only a real browser vouches for is that both screens
 * render and behave with JavaScript running.
 */

test('a customer can reach the photo flow from the home page', function () {
    $page = visit('/');

    $page->assertSee('Prints from your phone, ready in minutes.')
        ->screenshot(false, 'demo-01-home.png');

    $page->click('Send us your photos')
        ->assertPathIs('/photo')
        ->assertSee('Choose your photos')
        ->assertSee('Add photos')
        ->assertNoJavaScriptErrors()
        ->screenshot(false, 'demo-02-photo-start.png');
});

test('staff land on the fulfillment console and see the queue', function () {
    User::factory()->role(Role::Manager)->create([
        'email' => 'manager@example.com',
        'name' => 'Demo Manager',
    ]);

    PrintOrder::query()->forceCreate([
        'code' => 'HPL-4KD2',
        'channel' => PrintChannel::InStore,
        'status' => PrintOrderStatus::Received,
        'payment_status' => 'pay_at_counter',
        'customer_name' => 'Jordan Reyes',
        'prints_total_cents' => 999,
        'list_total_cents' => 1197,
        'savings_cents' => 198,
    ]);

    // The tablet's home-screen icon: /fulfill directly, which bounces to
    // the login and back once the staff member clicks their quick login.
    $page = visit('/fulfill');

    $page->assertPathIs('/login')
        ->click('Demo Manager')
        ->assertPathIs('/fulfill')
        ->assertSee('Fulfillment console')
        ->assertSee('HPL-4KD2')
        ->assertSee('Jordan Reyes')
        ->assertNoJavaScriptErrors()
        ->screenshot(false, 'demo-03-fulfillment-console.png');
});
