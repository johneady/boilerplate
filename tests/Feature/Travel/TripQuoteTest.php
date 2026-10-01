<?php

use App\Models\Tour;
use App\Models\TourDeparture;
use App\Travel\TripQuote;

beforeEach(function (): void {
    config(['travel.child_price_percent' => 75, 'travel.deposit_percent' => 20]);
});

test('a couple pays twice the adult price with no supplement', function () {
    $tour = Tour::factory()->create(['price_per_person_cents' => 289000, 'single_supplement_cents' => 69000]);

    $quote = TripQuote::for($tour, null, adults: 2);

    expect($quote->totalCents())->toBe(578000)
        ->and($quote->singleSupplementCents)->toBe(0);
});

test('a lone adult pays the single supplement', function () {
    $tour = Tour::factory()->create(['price_per_person_cents' => 289000, 'single_supplement_cents' => 69000]);

    expect(TripQuote::for($tour, null, adults: 1)->totalCents())->toBe(358000);
});

test('an adult travelling with a child pays no supplement and the child pays the child rate', function () {
    $tour = Tour::factory()->create(['price_per_person_cents' => 200000, 'single_supplement_cents' => 50000]);

    $quote = TripQuote::for($tour, null, adults: 1, children: 1);

    expect($quote->childPriceCents)->toBe(150000)
        ->and($quote->totalCents())->toBe(350000);
});

test('a departure price overrides the tour price', function () {
    $tour = Tour::factory()->create(['price_per_person_cents' => 200000]);
    $departure = TourDeparture::factory()->for($tour)->create(['price_per_person_cents' => 180000]);

    expect(TripQuote::for($tour, $departure, adults: 2)->totalCents())->toBe(360000);
});

test('the deposit is the configured share rounded up to the whole dollar', function () {
    $tour = Tour::factory()->create(['price_per_person_cents' => 333333, 'single_supplement_cents' => 0]);

    // 20% of $3,333.33 is $666.67, rounded up to $667.
    expect(TripQuote::for($tour, null, adults: 1)->depositCents())->toBe(66700);
});

test('a party with no adults cannot be quoted', function () {
    TripQuote::for(Tour::factory()->create(), null, adults: 0, children: 2);
})->throws(InvalidArgumentException::class);
