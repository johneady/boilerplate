<?php

use App\Livewire\CompareCars;
use App\Models\Vehicle;
use Livewire\Livewire;

test('the comparison starts with the first cars of the range', function () {
    $first = Vehicle::factory()->published()->create(['sort_order' => 1]);
    $second = Vehicle::factory()->published()->create(['sort_order' => 2]);

    Livewire::test(CompareCars::class)
        ->assertSet('selected', [$first->slug, $second->slug]);
});

test('the comparison keeps only published cars named in the url', function () {
    $published = Vehicle::factory()->published()->create();
    $draft = Vehicle::factory()->create();

    Livewire::withQueryParams(['cars' => [$draft->slug, $published->slug, 'no-such-car']])
        ->test(CompareCars::class)
        ->assertSet('selected', [$published->slug]);
});

test('the figures come from each car and the best one is marked', function () {
    Vehicle::factory()->published()->create(['name' => 'Voltiva Short', 'range_km' => 75, 'sort_order' => 1]);
    Vehicle::factory()->published()->create(['name' => 'Voltiva Long', 'range_km' => 177, 'sort_order' => 2]);

    Livewire::test(CompareCars::class)
        ->assertSeeInOrder(['Range', 'Up to 75 km', 'Up to 177 km', 'Best']);
});

test('adding a car past the limit drops the oldest choice', function () {
    $vehicles = Vehicle::factory()->published()->count(5)->sequence(fn ($sequence) => ['sort_order' => $sequence->index])->create();

    Livewire::test(CompareCars::class)
        ->call('toggle', $vehicles[4]->slug)
        ->assertSet('selected', [$vehicles[1]->slug, $vehicles[2]->slug, $vehicles[3]->slug, $vehicles[4]->slug]);
});
