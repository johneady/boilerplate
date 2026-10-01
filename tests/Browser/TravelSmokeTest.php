<?php

use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TripInquiry;

/*
 * The main traveller journey in a real browser: from a tour page, pick a date
 * from the dates table, watch the live quote and send a booking request. The
 * feature suite covers the server side; this catches the Alpine and Livewire
 * wiring between the dates table and the form.
 */

test('a traveller can pick a date and request to book a tour', function () {
    $tour = Tour::factory()->create(['name' => 'Iceland Ring Road', 'price_per_person_cents' => 200000]);
    TourDeparture::factory()->for($tour)->create(['starts_on' => now()->addMonth()->toDateString()]);
    $later = TourDeparture::factory()->for($tour)->create([
        'starts_on' => now()->addMonths(3)->toDateString(),
        'price_per_person_cents' => 180000,
    ]);

    $page = visit(route('tours.show', $tour, absolute: false));

    $page->assertSee('Iceland Ring Road')
        ->click('[data-test="departure-row"]:nth-child(2) button')
        ->assertSee('$3,600')
        ->fill('[wire\\:model="name"]', 'Ada Traveller')
        ->fill('[wire\\:model="email"]', 'ada@example.test')
        ->wait(3)
        ->press('Request to book')
        ->assertSee('Request received!')
        ->assertNoJavaScriptErrors();

    expect(TripInquiry::sole()->tour_departure_id)->toBe($later->id);
});
