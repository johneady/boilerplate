<?php

namespace App\Livewire\Prints;

use App\Media\MediaCollection;
use App\Media\MediaManager;
use App\Models\PrintLocation;
use App\Models\PrintOrder;
use App\Models\PrintOrderItem;
use App\Prints\Enums\PrintChannel;
use App\Prints\Enums\PrintOrderStatus;
use App\Prints\Enums\PrintPaymentStatus;
use App\Prints\PrintPricing;
use App\Prints\PrintQuote;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The branded phone flow a QR code or website link drops a customer into.
 *
 * The same component serves both halves of the client's requirement, told
 * apart by whether a location was scanned: in-store orders skip the payment
 * request entirely (that is the point) and end at a pickup code, while remote
 * orders collect a mailing address and take the demo checkout before their
 * photos are ever queued.
 */
class PhotoOrder extends Component
{
    use WithFileUploads;

    /**
     * The counter whose QR code was scanned, or null for a remote order.
     * Named differently from the route parameter so Livewire cannot mistake
     * the scanned slug for the property: the two are connected only by
     * mount(), which resolves the slug or 404s.
     */
    public ?PrintLocation $counter = null;

    /** @var list<TemporaryUploadedFile> */
    public array $photos = [];

    /** @var array<int, int> print counts, keyed by photo position */
    public array $quantities = [];

    public int $step = 1;

    public string $customerName = '';

    public string $customerEmail = '';

    public string $customerPhone = '';

    public string $mailingAddress = '';

    // The demo checkout's fields. Nothing real is charged -- any filled card
    // details succeed -- but they are collected exactly where a real
    // smartphone-centric checkout would sit, so swapping in Stripe or PayPal
    // later touches this component alone.
    public string $cardNumber = '';

    public string $cardExpiry = '';

    public string $cardCvc = '';

    public ?PrintOrder $order = null;

    public function mount(string $location = ''): void
    {
        if ($location !== '') {
            $scanned = PrintLocation::query()->where('slug', $location)->first();

            // A QR code on a wall points somewhere specific: a slug that
            // matches no counter, or a counter since retired, must 404 rather
            // than quietly become a mail-out order.
            if ($scanned === null || ! $scanned->is_active) {
                abort(404);
            }

            $this->counter = $scanned;
        }
    }

    /**
     * Validate each photo the moment it arrives, so a wrong file type or an
     * oversized shot is refused while the customer is still holding their
     * phone, not at the end of the flow.
     */
    public function updatedPhotos(): void
    {
        $this->validate([
            'photos' => ['required', 'array', 'max:20'],
            'photos.*' => app(MediaManager::class)->rulesFor(MediaCollection::OrderPhoto),
        ]);
    }

    /**
     * Drop one photo from the selection.
     */
    public function removePhoto(int $index): void
    {
        unset($this->photos[$index], $this->quantities[$index]);

        $this->photos = array_values($this->photos);
        $this->quantities = array_values($this->quantities);
    }

    /**
     * Move from choosing photos to choosing quantities.
     */
    public function chooseQuantities(): void
    {
        $this->validate([
            'photos' => ['required', 'array', 'min:1', 'max:20'],
            'photos.*' => app(MediaManager::class)->rulesFor(MediaCollection::OrderPhoto),
        ]);

        // Every photo starts at one print; the deal's nudge works up from there.
        $this->quantities = array_fill(0, count($this->photos), 1);

        $this->step = 2;
    }

    /**
     * Step a photo's print count up or down. One is the floor: a photo with
     * no prints wanted is a photo to remove, not a zero to carry.
     */
    public function adjustQuantity(int $index, int $delta): void
    {
        $current = (int) ($this->quantities[$index] ?? 1);

        $this->quantities[$index] = min(50, max(1, $current + $delta));
    }

    /**
     * Move from quantities to the customer details (and, for a remote order,
     * the checkout).
     */
    public function provideDetails(): void
    {
        $this->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $this->step = 3;
    }

    /**
     * Place the order: attach the photos, snapshot the pricing, and land on
     * the confirmation.
     */
    public function placeOrder(): void
    {
        $this->validate($this->detailRules());

        $quote = $this->quote();
        $channel = $this->channel();
        $manager = app(MediaManager::class);

        $order = DB::transaction(function () use ($manager, $quote, $channel): PrintOrder {
            $order = new PrintOrder;

            $order->forceFill([
                'code' => PrintOrder::generateCode(),
                'channel' => $channel,
                'print_location_id' => $this->counter?->getKey(),
                'status' => PrintOrderStatus::Received,
                'payment_status' => $channel === PrintChannel::Remote
                    ? PrintPaymentStatus::Paid
                    : PrintPaymentStatus::PayAtCounter,
                'customer_name' => $this->customerName,
                'customer_email' => $channel === PrintChannel::Remote ? $this->customerEmail : null,
                'customer_phone' => $this->customerPhone !== '' ? $this->customerPhone : null,
                'mailing_address' => $channel === PrintChannel::Remote ? $this->mailingAddress : null,
                'prints_total_cents' => $quote->totalCents,
                'list_total_cents' => $quote->listTotalCents,
                'savings_cents' => $quote->savingsCents,
            ]);

            $order->save();

            foreach ($this->photos as $index => $photo) {
                $media = $manager->attach(
                    file: $photo,
                    collection: MediaCollection::OrderPhoto,
                    owner: $order,
                );

                $item = new PrintOrderItem;

                $item->forceFill([
                    'print_order_id' => $order->getKey(),
                    'media_id' => $media->getKey(),
                    'quantity' => (int) ($this->quantities[$index] ?? 1),
                ])->save();
            }

            return $order;
        });

        $order->load('items.media');

        $this->order = $order;
        $this->step = 4;
    }

    /**
     * Start over, as the next customer at the counter would.
     */
    public function startAnother(): void
    {
        $this->reset([
            'photos', 'quantities', 'customerName', 'customerEmail', 'customerPhone',
            'mailingAddress', 'cardNumber', 'cardExpiry', 'cardCvc', 'order', 'step',
        ]);
    }

    /**
     * Which flow this is: a scanned counter, or the website's mail-out.
     */
    #[Computed]
    public function channel(): PrintChannel
    {
        return $this->counter !== null ? PrintChannel::InStore : PrintChannel::Remote;
    }

    /**
     * The live price of the current selection.
     */
    #[Computed]
    public function quote(): PrintQuote
    {
        return PrintPricing::quote(array_sum($this->quantities) ?: 0);
    }

    /**
     * The rules for the details step, which differ by channel: an in-store
     * customer owes a name and nothing else (never a payment request), while
     * a remote order needs somewhere to post the prints and the demo
     * checkout's card.
     *
     * @return array<string, mixed>
     */
    private function detailRules(): array
    {
        $rules = [
            'customerName' => ['required', 'string', 'min:2', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:32'],
        ];

        if ($this->channel() === PrintChannel::Remote) {
            $rules['customerEmail'] = ['required', 'email', 'max:255'];
            $rules['mailingAddress'] = ['required', 'string', 'min:8', 'max:1024'];
            $rules['cardNumber'] = ['required', 'string', 'min:12', 'max:32'];
            $rules['cardExpiry'] = ['required', 'string', 'min:4', 'max:7'];
            $rules['cardCvc'] = ['required', 'string', 'digits_between:3,4'];
        }

        return $rules;
    }

    public function render(): View
    {
        return view('livewire.prints.photo-order')
            ->layout('layouts.print', ['locationName' => $this->counter?->name]);
    }
}
