<?php

use App\Bakery\DietaryTag;
use App\Bakery\MenuCategory;
use App\Models\MenuItem;

test('the home page lists available items under their category', function () {
    MenuItem::factory()->create([
        'name' => 'Country Sourdough Loaf',
        'category' => MenuCategory::Breads,
        'price_cents' => 900,
        'price_unit' => 'per loaf',
        'dietary' => [DietaryTag::Vegan->value],
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSeeInOrder(['Breads', 'Country Sourdough Loaf', '$9.00', 'per loaf', 'Vegan']);
});

test('the home page leaves out items switched off the menu', function () {
    MenuItem::factory()->unavailable()->create(['name' => 'Sold Out Scones']);

    $this->get('/')->assertDontSee('Sold Out Scones');
});

test('a category with nothing available is not shown', function () {
    MenuItem::factory()->create(['category' => MenuCategory::Breads]);

    $this->get('/')->assertDontSee(MenuCategory::PiesAndTarts->tagline());
});

test('each item links to the order form with itself chosen', function () {
    MenuItem::factory()->create(['slug' => 'cinnamon-rolls']);

    $this->get('/')->assertSee(route('order', ['item' => 'cinnamon-rolls']), false);
});

test('the home page explains itself when the menu is empty', function () {
    $this->get('/')->assertSee('The menu is being written.');
});
