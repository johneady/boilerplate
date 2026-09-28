<?php

use App\Livewire\Prints\PhotoOrder;
use App\Media\MediaCollection;
use App\Models\PrintLocation;
use App\Models\PrintOrder;
use App\Prints\Enums\PrintChannel;
use App\Prints\Enums\PrintPaymentStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->counter = PrintLocation::query()->forceCreate([
        'slug' => 'front-counter',
        'name' => 'Front counter',
        'address' => '1122 Wharf Road, Rockport',
        'is_active' => true,
    ]);
});

/**
 * Drive the wizard as a customer would: photos in, quantities stepped,
 * details filled, order placed.
 */
function uploadWizard(array $photos)
{
    return Livewire::test(PhotoOrder::class)
        ->set('photos', $photos)
        ->call('chooseQuantities');
}

test('a scanned counter opens the in-store flow and the plain route the mail-out', function () {
    $inStore = Livewire::test(PhotoOrder::class, ['location' => 'front-counter']);

    expect($inStore->get('counter')->id)->toBe($this->counter->id);

    $remote = Livewire::test(PhotoOrder::class);

    expect($remote->get('counter'))->toBeNull();
});

test('a scanned counter that does not exist is not a mail-out order', function () {
    $this->get('photo/retired-counter')->assertNotFound();
});

test('an in-store order is placed with no payment step and a code to read out', function () {
    $wizard = Livewire::test(PhotoOrder::class, ['location' => 'front-counter'])
        ->set('photos', [
            UploadedFile::fake()->image('sunset.jpg', 1200, 900),
            UploadedFile::fake()->image('boat.jpg', 1200, 900),
        ])
        ->call('chooseQuantities')
        // Two of the first photo, one of the second: three prints, the bundle.
        ->call('adjustQuantity', 0, 1)
        ->call('provideDetails')
        ->set('customerName', 'Jordan Reyes')
        ->call('placeOrder')
        ->assertSet('step', 4);

    $order = PrintOrder::query()->sole();

    expect($order->channel)->toBe(PrintChannel::InStore)
        ->and($order->print_location_id)->toBe($this->counter->id)
        ->and($order->payment_status)->toBe(PrintPaymentStatus::PayAtCounter)
        ->and($order->prints_total_cents)->toBe(999)
        ->and($order->savings_cents)->toBe(198)
        ->and($order->code)->toStartWith('HPL-')
        ->and($order->items()->count())->toBe(2)
        // The photos travelled with the order as re-encoded media, not as
        // anything the customer's phone still holds.
        ->and($order->getMedia(MediaCollection::OrderPhoto))->toHaveCount(2)
        ->and($order->items->sum('quantity'))->toBe(3);

    $wizard->assertSee($order->code);
});

test('a remote order collects an address, takes the checkout, and is owed nothing at pickup', function () {
    $wizard = uploadWizard([UploadedFile::fake()->image('heron.jpg', 1200, 900)])
        ->call('provideDetails')
        ->set('customerName', 'Priya Anand')
        ->set('customerEmail', 'priya@example.com')
        ->set('mailingAddress', "612 Saltgrass Way\nCorpus Christi, TX 78412")
        ->set('cardNumber', '4242 4242 4242 4242')
        ->set('cardExpiry', '12/28')
        ->set('cardCvc', '123')
        ->call('placeOrder')
        ->assertSet('step', 4);

    $order = PrintOrder::query()->sole();

    expect($order->channel)->toBe(PrintChannel::Remote)
        ->and($order->payment_status)->toBe(PrintPaymentStatus::Paid)
        ->and($order->mailing_address)->toContain('Saltgrass Way')
        ->and($order->prints_total_cents)->toBe(399);
});

test('a remote order will not place without its address and card', function () {
    $wizard = uploadWizard([UploadedFile::fake()->image('heron.jpg', 1200, 900)])
        ->call('provideDetails')
        ->set('customerName', 'Priya Anand')
        ->call('placeOrder')
        ->assertHasErrors(['customerEmail', 'mailingAddress', 'cardNumber']);

    expect(PrintOrder::query()->exists())->toBeFalse();
});

test('the flow holds the line at twenty photos', function () {
    $wizard = Livewire::test(PhotoOrder::class)
        ->set('photos', array_map(
            fn (int $i): UploadedFile => UploadedFile::fake()->image("photo-{$i}.jpg", 800, 600),
            range(1, 21),
        ));

    // The twenty-first photo is refused the moment it arrives.
    $wizard->assertHasErrors(['photos']);

    expect($wizard->get('step'))->toBe(1);
});
