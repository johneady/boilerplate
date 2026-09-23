<?php

use App\Bakery\InquiryStatus;
use App\Livewire\OrderInquiryForm;
use App\Models\MenuItem;
use App\Models\OrderInquiry;
use App\Notifications\OrderInquiryAcknowledged;
use App\Notifications\OrderInquiryReceived;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    Notification::fake();

    app(Settings::class)->set(SettingKey::BusinessEmail, 'baker@example.com');

    // The limiter is keyed on the request IP, the same for every test here.
    RateLimiter::clear('order-inquiry:127.0.0.1');
});

/**
 * Fill and submit the form for one item. Travels past the minimum fill time,
 * as the contact form tests do, so the submission is not treated as a bot.
 *
 * @param  array<string, mixed>  $overrides
 */
function submitOrderInquiry(MenuItem $item, array $overrides = []): Testable
{
    $component = Livewire::test(OrderInquiryForm::class)
        ->set('lines', [['menuItemId' => (string) $item->id, 'quantity' => 2]])
        ->set('neededOn', now()->addDays(10)->toDateString())
        ->set('name', 'Sam Visitor')
        ->set('email', 'sam@example.test');

    foreach ($overrides as $property => $value) {
        $component->set($property, $value);
    }

    test()->travel(5)->seconds();

    return $component->call('submit');
}

test('the order page renders the available menu for a guest', function () {
    MenuItem::factory()->create(['name' => 'Cinnamon Rolls']);
    MenuItem::factory()->unavailable()->create(['name' => 'Sold Out Scones']);

    $this->get('/order')
        ->assertSuccessful()
        ->assertSee('Request an order')
        ->assertSee('Cinnamon Rolls')
        ->assertDontSee('Sold Out Scones');
});

test('a menu link preselects its item on the form', function () {
    $item = MenuItem::factory()->create(['slug' => 'cinnamon-rolls']);

    Livewire::withQueryParams(['item' => 'cinnamon-rolls'])
        ->test(OrderInquiryForm::class)
        ->assertSet('lines.0.menuItemId', (string) $item->id);
});

test('an unknown item in the link is ignored', function () {
    MenuItem::factory()->create(['slug' => 'cinnamon-rolls']);

    Livewire::withQueryParams(['item' => 'no-such-thing'])
        ->test(OrderInquiryForm::class)
        ->assertSet('lines.0.menuItemId', '');
});

test('the estimate follows the chosen items and quantities', function () {
    $rolls = MenuItem::factory()->create(['price_cents' => 2400]);
    $macarons = MenuItem::factory()->create(['price_cents' => 3200]);

    $component = Livewire::test(OrderInquiryForm::class)
        ->set('lines', [
            ['menuItemId' => (string) $rolls->id, 'quantity' => 3],
            ['menuItemId' => (string) $macarons->id, 'quantity' => 1],
        ]);

    expect($component->instance()->estimateCents())->toBe(10400);
});

test('a submission is stored with a snapshot of each line', function () {
    $item = MenuItem::factory()->create(['name' => 'Cinnamon Rolls', 'price_unit' => 'half dozen', 'price_cents' => 2400]);

    submitOrderInquiry($item, ['details' => 'For a team breakfast.'])
        ->assertHasNoErrors()
        ->assertSee('Your order request is in!');

    $inquiry = OrderInquiry::sole();

    expect($inquiry->reference)->toStartWith('HH-')
        ->and($inquiry->status)->toBe(InquiryStatus::New)
        ->and($inquiry->estimated_total_cents)->toBe(4800)
        ->and($inquiry->details)->toBe('For a team breakfast.')
        ->and($inquiry->items)->toHaveCount(1)
        ->and($inquiry->items[0])->toEqualCanonicalizing([
            'menu_item_id' => $item->id,
            'name' => 'Cinnamon Rolls',
            'price_unit' => 'half dozen',
            'price_cents' => 2400,
            'quantity' => 2,
        ]);
});

/**
 * A later menu edit must not rewrite what somebody asked for.
 */
test('a stored inquiry keeps the price the customer saw', function () {
    $item = MenuItem::factory()->create(['price_cents' => 2400]);

    submitOrderInquiry($item);

    $item->update(['price_cents' => 9900, 'name' => 'Renamed']);

    expect(OrderInquiry::sole()->items[0]['price_cents'])->toBe(2400);
});

test('the same item on two lines is merged into one', function () {
    $item = MenuItem::factory()->create();

    submitOrderInquiry($item, ['lines' => [
        ['menuItemId' => (string) $item->id, 'quantity' => 2],
        ['menuItemId' => (string) $item->id, 'quantity' => 3],
    ]]);

    expect(OrderInquiry::sole()->items)->toHaveCount(1)
        ->and(OrderInquiry::sole()->items[0]['quantity'])->toBe(5);
});

test('the baker is alerted and the customer acknowledged', function () {
    submitOrderInquiry(MenuItem::factory()->create());

    Notification::assertSentOnDemand(
        OrderInquiryReceived::class,
        fn (OrderInquiryReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'baker@example.com',
    );

    Notification::assertSentOnDemand(
        OrderInquiryAcknowledged::class,
        fn (OrderInquiryAcknowledged $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'sam@example.test',
    );
});

test('a blank business email still acknowledges the customer but alerts nobody', function () {
    app(Settings::class)->set(SettingKey::BusinessEmail, '');

    submitOrderInquiry(MenuItem::factory()->create());

    Notification::assertSentOnDemandTimes(OrderInquiryReceived::class, 0);
    Notification::assertSentOnDemandTimes(OrderInquiryAcknowledged::class, 1);
});

test('a date inside the longest item notice is rejected', function () {
    $cake = MenuItem::factory()->create(['notice_days' => 7]);

    submitOrderInquiry($cake, ['neededOn' => now()->addDays(4)->toDateString()])
        ->assertHasErrors(['neededOn' => 'after_or_equal']);

    expect(OrderInquiry::count())->toBe(0);
});

test('a date beyond the booking window is rejected', function () {
    submitOrderInquiry(MenuItem::factory()->create(), [
        'neededOn' => now()->addDays((int) config('bakery.booking_window_days') + 5)->toDateString(),
    ])->assertHasErrors(['neededOn' => 'before_or_equal']);
});

test('delivery needs an address', function () {
    submitOrderInquiry(MenuItem::factory()->create(), ['fulfilment' => 'delivery'])
        ->assertHasErrors(['deliveryAddress' => 'required_if']);
});

test('an address is kept only for delivery', function () {
    submitOrderInquiry(MenuItem::factory()->create(), ['fulfilment' => 'pickup', 'deliveryAddress' => '12 Oak Street']);

    expect(OrderInquiry::sole()->delivery_address)->toBeNull();
});

test('an item switched off the menu cannot be ordered', function () {
    $item = MenuItem::factory()->unavailable()->create();

    submitOrderInquiry($item)->assertHasErrors(['lines.0.menuItemId' => 'exists']);
});

test('a quantity above the limit is rejected', function () {
    submitOrderInquiry(MenuItem::factory()->create(), ['lines' => [
        ['menuItemId' => (string) MenuItem::query()->value('id'), 'quantity' => (int) config('bakery.max_quantity') + 1],
    ]])->assertHasErrors(['lines.0.quantity' => 'max']);
});

test('a filled honeypot reports success without storing or sending', function () {
    submitOrderInquiry(MenuItem::factory()->create(), ['website' => 'https://spam.example'])
        ->assertHasNoErrors();

    expect(OrderInquiry::count())->toBe(0);
    Notification::assertNothingSent();
});

test('a submission faster than a person can type is not stored', function () {
    $item = MenuItem::factory()->create();

    Livewire::test(OrderInquiryForm::class)
        ->set('lines', [['menuItemId' => (string) $item->id, 'quantity' => 1]])
        ->set('neededOn', now()->addDays(10)->toDateString())
        ->set('name', 'Bot')
        ->set('email', 'bot@example.test')
        ->call('submit');

    expect(OrderInquiry::count())->toBe(0);
});

test('an address that has sent several requests is turned away', function () {
    RateLimiter::increment('order-inquiry:127.0.0.1', 3600, 5);

    submitOrderInquiry(MenuItem::factory()->create())->assertHasErrors('name');

    expect(OrderInquiry::count())->toBe(0);
});

test('a mail failure does not lose the inquiry', function () {
    Notification::shouldReceive('route')->andThrow(new RuntimeException('SMTP is down'));

    submitOrderInquiry(MenuItem::factory()->create())->assertHasNoErrors();

    expect(OrderInquiry::count())->toBe(1);
});
