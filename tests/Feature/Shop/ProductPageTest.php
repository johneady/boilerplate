<?php

use App\Livewire\Shop\ShowPackage;
use App\Models\Package;
use App\Models\User;
use App\Shop\Cart;
use Livewire\Livewire;

test('a package page shows its price and specs', function () {
    $package = Package::factory()->active()->create([
        'title' => 'Santorini Caldera',
        'price_cents' => 24900,
        'resolution' => '5.4K',
        'clip_count' => 12,
        'duration_seconds' => 486,
    ]);

    $this->get(route('shop.show', $package))
        ->assertSuccessful()
        ->assertSee('Santorini Caldera')
        ->assertSee('$249.00')
        ->assertSee('5.4K')
        ->assertSee('8:06')
        ->assertSee('<title>Santorini Caldera - ', false);
});

test('a package description is rendered as markdown with html escaped', function () {
    $package = Package::factory()->active()->create([
        'description' => "**Golden hour**\n\n<script>alert(1)</script>",
    ]);

    $this->get(route('shop.show', $package))
        ->assertSee('<strong>Golden hour</strong>', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('a package that is not on sale is a 404 for the public', function () {
    $package = Package::factory()->create();

    $this->get(route('shop.show', $package))->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get(route('shop.show', $package))
        ->assertNotFound();
});

test('an administrator can preview a package that is not on sale', function () {
    $package = Package::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('shop.show', $package))
        ->assertSuccessful()
        ->assertSee('This package is not on sale.');
});

test('adding a package puts it in the basket once', function () {
    $package = Package::factory()->active()->create();

    Livewire::test(ShowPackage::class, ['package' => $package])
        ->call('addToCart')
        ->assertDispatched('cart-updated')
        ->call('addToCart');

    expect(app(Cart::class)->ids())->toBe([$package->id]);
});

test('buy now adds the package and goes to the basket', function () {
    $package = Package::factory()->active()->create();

    Livewire::test(ShowPackage::class, ['package' => $package])
        ->call('buyNow')
        ->assertRedirect(route('cart'));

    expect(app(Cart::class)->has($package->id))->toBeTrue();
});

test('a package that sold out while the page was open cannot be added', function () {
    $package = Package::factory()->active()->limited(1)->create();

    $component = Livewire::test(ShowPackage::class, ['package' => $package]);

    $package->update(['stock' => 0]);

    $component->call('buyNow')->assertNoRedirect();

    expect(app(Cart::class)->count())->toBe(0);
});
