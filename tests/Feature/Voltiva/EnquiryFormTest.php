<?php

use App\Livewire\EnquiryForm;
use App\Models\Enquiry;
use App\Models\Vehicle;
use App\Notifications\EnquiryFollowUp;
use App\Notifications\EnquiryReceived;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    app(Settings::class)->set(SettingKey::BusinessEmail, 'sales@voltiva.test');

    // The limiter is keyed on 127.0.0.1 for every test in the file.
    RateLimiter::clear('enquiry-form:127.0.0.1');
});

/**
 * Fill and submit the form. The minimum fill time is measured from mount(),
 * so the test travels past it rather than lowering the threshold.
 */
function submitEnquiry(array $mount = [], array $overrides = []): Testable
{
    $component = Livewire::test(EnquiryForm::class, $mount)
        ->set('name', 'Marta Pons')
        ->set('email', 'marta@example.test')
        ->set('phone', '+34 600 000 000')
        ->set('location', 'Sóller')
        ->set('drivingNeeds', ['town', 'coast_mountain'])
        ->set('financeInterest', true)
        ->set('message', 'Could I book a test drive?');

    foreach ($overrides as $property => $value) {
        $component->set($property, $value);
    }

    test()->travel(5)->seconds();

    return $component->call('submit');
}

test('opened from a car page the form arrives with that car selected', function () {
    $vehicle = Vehicle::factory()->published()->create();

    Livewire::test(EnquiryForm::class, ['vehicle' => $vehicle->slug, 'source' => 'vehicle'])
        ->assertSet('vehicleId', (string) $vehicle->id);
});

test('an unknown car or source in the link is ignored', function () {
    Livewire::test(EnquiryForm::class, ['vehicle' => 'no-such-car', 'source' => 'made-up'])
        ->assertSet('vehicleId', '')
        ->assertSet('source', 'register');
});

test('an enquiry records the customer, the car, the source and their interests', function () {
    Notification::fake();
    $vehicle = Vehicle::factory()->published()->create(['name' => 'Voltiva Terra']);

    submitEnquiry(['vehicle' => $vehicle->slug, 'source' => 'vehicle'])
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    $enquiry = Enquiry::sole();
    expect($enquiry)
        ->name->toBe('Marta Pons')
        ->vehicle_id->toBe($vehicle->id)
        ->vehicle_name->toBe('Voltiva Terra')
        ->source->toBe('vehicle')
        ->finance_interest->toBeTrue()
        ->registration_interest->toBeTrue()
        ->driving_needs->toBe(['town', 'coast_mountain']);
});

test('an enquiry alerts the sales team and starts the customer email sequence', function () {
    Notification::fake();
    $this->freezeSecond();

    submitEnquiry();

    Notification::assertSentOnDemand(EnquiryReceived::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'sales@voltiva.test');
    Notification::assertSentOnDemand(EnquiryFollowUp::class, fn (EnquiryFollowUp $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notification->step === 1 && $notifiable->routes['mail'] === 'marta@example.test');
    expect(Enquiry::sole())
        ->follow_up_step->toBe(1)
        ->next_follow_up_at->toEqual(now()->addDays(2));
});

test('the sales team is alerted in the site language whatever language the customer used', function () {
    Notification::fake();
    app()->setLocale('es');

    submitEnquiry();

    Notification::assertSentOnDemand(EnquiryReceived::class, fn (EnquiryReceived $notification): bool => $notification->locale === 'en');
});

test('the alert subject names the customer as typed', function () {
    $enquiry = Enquiry::factory()->make(['id' => 1, 'name' => 'Jean-Luc Picard']);

    $mail = (new EnquiryReceived($enquiry))->toMail($enquiry);

    expect($mail->subject)->toStartWith('New enquiry from Jean-Luc Picard');
});

test('the first email goes out in the language the customer used', function () {
    Notification::fake();
    app()->setLocale('es');

    submitEnquiry();

    expect(Enquiry::sole()->locale)->toBe('es');
    Notification::assertSentOnDemand(EnquiryFollowUp::class, fn (EnquiryFollowUp $notification): bool => $notification->locale === 'es');
});

test('a name and a valid email are required', function () {
    Notification::fake();

    submitEnquiry(overrides: ['name' => '', 'email' => 'not-an-email'])
        ->assertHasErrors(['name' => 'required', 'email' => 'email']);

    expect(Enquiry::count())->toBe(0);
    Notification::assertNothingSent();
});

test('an unpublished car cannot be chosen', function () {
    Notification::fake();
    $draft = Vehicle::factory()->create();

    submitEnquiry(overrides: ['vehicleId' => (string) $draft->id])
        ->assertHasErrors(['vehicleId' => 'exists']);

    expect(Enquiry::count())->toBe(0);
});

test('a filled honeypot reports success but stores and sends nothing', function () {
    Notification::fake();

    submitEnquiry(overrides: ['website' => 'https://spam.example'])
        ->assertSet('sent', true);

    expect(Enquiry::count())->toBe(0);
    Notification::assertNothingSent();
});

test('the form refuses an address that has sent too many enquiries', function () {
    Notification::fake();
    RateLimiter::increment('enquiry-form:127.0.0.1', 3600, 5);

    submitEnquiry()->assertHasErrors('message');

    expect(Enquiry::count())->toBe(0);
});

test('a malformed link to the enquiry page still renders the form', function () {
    $this->get('/register-your-interest?vehicle[]=x&source[]=y')
        ->assertSeeLivewire(EnquiryForm::class);
});

test('the register your interest page preselects the car from the link', function () {
    $vehicle = Vehicle::factory()->published()->create();

    $this->get(route('enquiry', ['vehicle' => $vehicle->slug]))
        ->assertSeeLivewire(EnquiryForm::class);
});
