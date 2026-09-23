<?php

use App\Models\User;
use App\Models\Vehicle;

test('the range lists published cars only', function () {
    $published = Vehicle::factory()->published()->create(['name' => 'Voltiva Published']);
    Vehicle::factory()->create(['name' => 'Voltiva Draft']);

    // A draft appears nowhere, header menu included.
    $this->get(route('cars.index'))
        ->assertSee($published->name)
        ->assertDontSee('Voltiva Draft');
});

test('a category page lists only cars of that class and explains the licence', function () {
    Vehicle::factory()->published()->create(['name' => 'Voltiva Town']);
    $island = Vehicle::factory()->published()->l7e()->create(['name' => 'Voltiva Island']);

    // Asserted on the listing's own data: the header's Cars menu names
    // every car on every page, so the page text cannot show the filter.
    $this->get(route('cars.category', 'l7e'))
        ->assertViewHas('vehicles', fn ($vehicles): bool => $vehicles->modelKeys() === [$island->id])
        ->assertSee('B1 licence (from age 16) or a B car licence');
});

test('an unknown category is not found', function () {
    $this->get('/cars/l9e')->assertNotFound();
});

test('the product page leads with benefits and explains the jargon', function () {
    $vehicle = Vehicle::factory()->published()->create([
        'range_km' => 150,
        'battery_voltage' => 72,
        'battery_capacity_ah' => 150,
        'battery_chemistry' => 'LiFePO4',
    ]);

    $this->get(route('cars.show', $vehicle))
        ->assertSeeInOrder(['More range between charges', 'Up to 150 km', '72V 150'])
        // The glossary explanation travels with the term.
        ->assertSee('Ah (amp-hours)')
        ->assertSee('LiFePO4 (lithium iron phosphate)');
});

test('the product page publishes the car as structured data with its price', function () {
    $vehicle = Vehicle::factory()->published()->create(['price_cents' => 1449000]);

    $this->get(route('cars.show', $vehicle))
        ->assertSee('"@type":"Car"', false)
        ->assertSee('"price":"14490.00"', false);
});

test('the product page ends with the enquiry form', function () {
    $vehicle = Vehicle::factory()->published()->create();

    $this->get(route('cars.show', $vehicle))
        ->assertSeeLivewire('enquiry-form')
        ->assertSee('Interested in the '.$vehicle->name.'?');
});

test('an unpublished car is hidden from the public', function () {
    $vehicle = Vehicle::factory()->create();

    $this->get(route('cars.show', $vehicle))->assertNotFound();
});

test('an unpublished car can be previewed by someone who may edit it', function () {
    $vehicle = Vehicle::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('cars.show', $vehicle))
        ->assertSee('This car is not published yet.');
});
