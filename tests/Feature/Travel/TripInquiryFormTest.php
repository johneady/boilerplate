<?php

use App\Livewire\Travel\TripInquiryForm;
use App\Models\Destination;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TripInquiry;
use App\Models\User;
use App\Notifications\TripInquiryAcknowledged;
use App\Notifications\TripInquiryReceived;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function (): void {
    Notification::fake();
    app(Settings::class)->set(SettingKey::BusinessEmail, 'trips@example.com');
    RateLimiter::clear('trip-inquiry:127.0.0.1');
    config(['travel.child_price_percent' => 75]);
});

test('a traveller can request seats on a departure and is shown a reference', function () {
    $tour = Tour::factory()->create(['price_per_person_cents' => 200000]);
    $departure = TourDeparture::factory()->for($tour)->create();

    $component = Livewire::test(TripInquiryForm::class, ['tour' => $tour])
        ->set('departureId', $departure->id)
        ->set('adults', 2)
        ->set('childCount', 1)
        ->set('name', 'Ada Traveller')
        ->set('email', 'ada@example.test')
        ->set('message', 'Vegetarian, please.');

    $this->travel(5)->seconds();
    $component->call('submit')->assertHasNoErrors();

    $inquiry = TripInquiry::sole();

    expect($inquiry->tour_departure_id)->toBe($departure->id)
        ->and($inquiry->destination_id)->toBe($tour->destination_id)
        ->and($inquiry->quoted_total_cents)->toBe(550000)
        ->and($inquiry->reference)->toStartWith('WL-');

    $component->assertSet('sentReference', $inquiry->reference)->assertSee($inquiry->reference);

    Notification::assertSentTo(new AnonymousNotifiable, TripInquiryReceived::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'trips@example.com');
    Notification::assertSentTo(new AnonymousNotifiable, TripInquiryAcknowledged::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'ada@example.test');
});

test('the live quote follows the party size', function () {
    $tour = Tour::factory()->create(['price_per_person_cents' => 200000, 'single_supplement_cents' => 50000]);
    TourDeparture::factory()->for($tour)->create();

    Livewire::test(TripInquiryForm::class, ['tour' => $tour])
        ->set('adults', 1)
        ->assertSee('$2,500')
        ->assertSee('Solo traveller supplement')
        ->set('adults', 3)
        ->assertSee('$6,000');
});

test('a party larger than the seats left is turned away', function () {
    $tour = Tour::factory()->create();
    $departure = TourDeparture::factory()->for($tour)->seatsLeft(2)->create();

    $component = Livewire::test(TripInquiryForm::class, ['tour' => $tour])
        ->set('departureId', $departure->id)
        ->set('adults', 3)
        ->set('name', 'Ada Traveller')
        ->set('email', 'ada@example.test');

    $this->travel(5)->seconds();
    $component->call('submit')->assertHasErrors('departureId')->assertSee('Only 2 seats are left on this departure.');

    expect(TripInquiry::count())->toBe(0);
});

test('a sold-out or past departure cannot be chosen', function () {
    $tour = Tour::factory()->create();
    TourDeparture::factory()->for($tour)->create();
    $soldOut = TourDeparture::factory()->for($tour)->seatsLeft(0)->create();
    $past = TourDeparture::factory()->for($tour)->create(['starts_on' => now()->subWeek()->toDateString()]);

    $component = Livewire::test(TripInquiryForm::class, ['tour' => $tour])
        ->set('name', 'Ada Traveller')
        ->set('email', 'ada@example.test');

    $this->travel(5)->seconds();

    foreach ([$soldOut, $past] as $departure) {
        $component->set('departureId', $departure->id)->call('submit')->assertHasErrors(['departureId' => 'in']);
    }

    expect(TripInquiry::count())->toBe(0);
});

test('a tailor-made request needs the trip described', function () {
    $component = Livewire::test(TripInquiryForm::class)
        ->set('name', 'Ada Traveller')
        ->set('email', 'ada@example.test');

    $this->travel(5)->seconds();
    $component->call('submit')->assertHasErrors(['message' => 'required']);
});

test('a tailor-made request is stored with its destination and month and no quote', function () {
    $destination = Destination::factory()->create(['slug' => 'iceland']);

    $component = Livewire::test(TripInquiryForm::class, ['destination' => 'iceland'])
        ->assertSet('destinationId', $destination->id)
        ->set('travelMonth', 'February 2027')
        ->set('name', 'Ada Traveller')
        ->set('email', 'ada@example.test')
        ->set('message', 'Northern lights for our honeymoon.');

    $this->travel(5)->seconds();
    $component->call('submit')->assertHasNoErrors();

    expect(TripInquiry::sole())
        ->tour_id->toBeNull()
        ->destination_id->toBe($destination->id)
        ->travel_month->toBe('February 2027')
        ->quoted_total_cents->toBeNull();
});

test('a filled honeypot looks successful but stores nothing', function () {
    $component = Livewire::test(TripInquiryForm::class)
        ->set('name', 'Bot')
        ->set('email', 'bot@example.test')
        ->set('message', 'Cheap pills')
        ->set('website', 'https://spam.example');

    $this->travel(5)->seconds();
    $component->call('submit')->assertHasNoErrors()->assertNotSet('sentReference', null);

    expect(TripInquiry::count())->toBe(0);
    Notification::assertNothingSent();
});

test('a signed-in traveller has the request linked to their account and sees it on their dashboard', function () {
    $user = User::factory()->create(['email' => 'ada@example.test']);
    $tour = Tour::factory()->create(['name' => 'Iceland Ring Road']);
    TourDeparture::factory()->for($tour)->create();

    $component = Livewire::actingAs($user)->test(TripInquiryForm::class, ['tour' => $tour])
        ->assertSet('email', 'ada@example.test');

    $this->travel(5)->seconds();
    $component->call('submit')->assertHasNoErrors();

    expect(TripInquiry::sole()->user_id)->toBe($user->id);

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Iceland Ring Road');
});

test('the dashboard does not show another traveller\'s requests', function () {
    TripInquiry::factory()->create(['email' => 'someone@example.test', 'travel_month' => 'Secret Month 2099']);

    $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->assertDontSee('Secret Month 2099');
});
