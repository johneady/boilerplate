<?php

use App\Filament\Resources\Destinations\Pages\ManageDestinations;
use App\Filament\Resources\Tours\Pages\ListTours;
use App\Livewire\Travel\TourFinder;
use App\Models\Destination;
use App\Models\Page;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\User;
use App\Travel\TourStyle;
use Livewire\Livewire;

test('the public travel pages render', function () {
    $tour = Tour::factory()->create(['name' => 'Iceland Ring Road', 'is_featured' => true]);
    TourDeparture::factory()->for($tour)->create();

    $this->get('/')->assertOk()->assertSee('Iceland Ring Road');
    $this->get('/destinations')->assertOk()->assertSee($tour->destination->name);
    $this->get(route('destinations.show', $tour->destination))->assertOk()->assertSee('Iceland Ring Road');
    $this->get(route('tours.show', $tour))->assertOk()->assertSee('Request to book');
    $this->get('/tours')->assertOk()->assertSee('Iceland Ring Road');
    $this->get('/plan-a-trip')->assertOk()->assertSee('Send my trip request');
});

test('an unpublished tour is hidden from the site', function () {
    $tour = Tour::factory()->unpublished()->create(['name' => 'Secret Tour']);

    $this->get(route('tours.show', $tour))->assertNotFound();

    Livewire::test(TourFinder::class)->assertDontSee('Secret Tour');
});

test('the tour finder filters by destination, style and length', function () {
    $iceland = Destination::factory()->create(['slug' => 'iceland']);
    Tour::factory()->for($iceland)->create(['name' => 'Ring Road', 'style' => TourStyle::Adventure, 'duration_days' => 10]);
    Tour::factory()->for($iceland)->create(['name' => 'Aurora Break', 'style' => TourStyle::Wildlife, 'duration_days' => 5]);
    Tour::factory()->create(['name' => 'Tuscany Wine', 'style' => TourStyle::FoodAndWine, 'duration_days' => 7]);

    Livewire::withQueryParams(['destination' => 'iceland'])->test(TourFinder::class)
        ->assertSee('Ring Road')->assertSee('Aurora Break')->assertDontSee('Tuscany Wine')
        ->set('style', 'wildlife')
        ->assertSee('Aurora Break')->assertDontSee('Ring Road')
        ->set('style', '')
        ->set('duration', 'short')
        ->assertSee('Aurora Break')->assertDontSee('Ring Road');
});

test('the travel paths cannot be taken by a content page', function () {
    expect(Page::RESERVED_SLUGS)->toContain('destinations', 'tours', 'plan-a-trip');
});

test('the admin tables link each photo at its public URL', function () {
    $destination = Destination::factory()->create(['image_path' => 'travel/lake-bled.jpg']);
    Tour::factory()->for($destination)->create();
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(ManageDestinations::class)
        ->assertSee(url('/storage/travel/lake-bled.jpg'), false)
        ->assertDontSee('/storage//storage/', false);

    // A tour without a photo of its own shows its destination's.
    Livewire::actingAs($admin)
        ->test(ListTours::class)
        ->assertSee(url('/storage/travel/lake-bled.jpg'), false);
});
