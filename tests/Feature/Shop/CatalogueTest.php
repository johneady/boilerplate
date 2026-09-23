<?php

use App\Livewire\Shop\Catalogue;
use App\Models\Package;
use App\Shop\Region;
use Livewire\Livewire;

test('the catalogue lists packages on sale and hides the rest', function () {
    Package::factory()->active()->create(['title' => 'Santorini Caldera']);
    Package::factory()->create(['title' => 'Unreleased Fjords']);

    $this->get(route('shop.index'))
        ->assertSuccessful()
        ->assertSee('Santorini Caldera')
        ->assertDontSee('Unreleased Fjords');
});

test('the catalogue filters by region', function () {
    Package::factory()->active()->create(['title' => 'Lake Bled', 'region' => Region::Europe]);
    Package::factory()->active()->create(['title' => 'Great Barrier Reef', 'region' => Region::Oceania]);

    Livewire::withQueryParams(['region' => 'oceania'])
        ->test(Catalogue::class)
        ->assertSee('Great Barrier Reef')
        ->assertDontSee('Lake Bled');
});

test('the catalogue searches by location', function () {
    Package::factory()->active()->create(['title' => 'Island Church', 'location' => 'Bled']);
    Package::factory()->active()->create(['title' => 'Glacier Flyover', 'location' => 'Wrangell']);

    Livewire::test(Catalogue::class)
        ->set('search', 'wrang')
        ->assertSee('Glacier Flyover')
        ->assertDontSee('Island Church');
});

test('the catalogue sorts by price', function () {
    Package::factory()->active()->create(['title' => 'Expensive Volcano', 'price_cents' => 59900]);
    Package::factory()->active()->create(['title' => 'Cheap Countryside', 'price_cents' => 9900]);

    Livewire::test(Catalogue::class)
        ->set('sort', 'price-asc')
        ->assertSeeInOrder(['Cheap Countryside', 'Expensive Volcano'])
        ->set('sort', 'price-desc')
        ->assertSeeInOrder(['Expensive Volcano', 'Cheap Countryside']);
});

test('a sold out or nearly sold out package says so on its card', function () {
    Package::factory()->active()->soldOut()->create();
    Package::factory()->active()->limited(2)->create();

    $this->get(route('shop.index'))
        ->assertSee('Sold out')
        ->assertSee('Only 2 licences left');
});

test('the home page features packages on sale', function () {
    Package::factory()->active()->create(['title' => 'Featured Reef', 'is_featured' => true]);
    Package::factory()->create(['title' => 'Hidden Draft', 'is_featured' => true]);

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('Featured Reef')
        ->assertDontSee('Hidden Draft');
});
