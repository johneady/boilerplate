<?php

use App\Auth\Role;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\TaxRate;
use App\Models\User;
use App\Notifications\Payments\PaymentReceipt;
use App\Payments\Actions\CapturePayment;
use App\Payments\Actions\RecordManualPayment;
use App\Payments\Actions\RefundPayment;
use App\Payments\Enums\Currency;
use App\Payments\Enums\ManualPaymentMethod;
use App\Payments\Money;
use App\Payments\ReceiptPdf;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Livewire\Livewire;
use Tests\Fixtures\Payments\HeldBooking;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
});

test('a paid payment\'s receipt downloads as a PDF', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    $response = $this->get($payment->receiptPdfUrl());

    $response->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertDownload('receipt-R-000001-test.pdf');

    expect($response->streamedContent())->toStartWith('%PDF-');
});

test('a live receipt is named for its number alone', function () {
    $payment = Payment::factory()->live()->make(['receipt_number' => 42]);

    expect(app(ReceiptPdf::class)->filename($payment))->toBe('receipt-R-000042.pdf');
});

test('a sandbox receipt says it is not a real one', function () {
    // Receipt numbers are counted per mode, so a test R-000001 and the first
    // live R-000001 must not be mistaken for each other.
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    expect(app(ReceiptPdf::class)->html($payment))->toContain('Test payment — not a valid receipt');

    $this->get($payment->receiptUrl())->assertSee('This is a test payment.');
});

test('a manual payment is dated by when the money arrived', function () {
    CarbonImmutable::setTestNow('2026-09-25 10:00');

    $payment = app(RecordManualPayment::class)->handle(
        payable: PaymentLink::factory()->create(),
        subtotal: Money::of(5000, Currency::CAD),
        method: ManualPaymentMethod::Cheque,
        reference: 'Cheque 1042',
        receivedOn: CarbonImmutable::parse('2026-09-01'),
        customerName: 'Sam Customer',
        customerEmail: 'sam@example.test',
        idempotencyKey: 'manual-key',
        recorder: User::factory()->admin()->create(),
    );

    expect(app(ReceiptPdf::class)->html($payment))
        ->toContain('Paid '.app(Settings::class)->formatDate(CarbonImmutable::parse('2026-09-01')))
        ->toContain('Cheque 1042');
});

test('a hold captured for less adds up to what was paid', function () {
    $payment = Payments::payWithDemo(HeldBooking::query()->create(['price' => 10000]));
    $payment = app(CapturePayment::class)->handle($payment, Money::of(6000, $payment->currency));

    expect(app(ReceiptPdf::class)->html($payment))
        ->toContain('Released, not charged')
        ->toContain('-CA$40.00')
        ->toContain('CA$60.00');
});

test('the PDF cannot be downloaded without its signature', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    $this->get(route('payments.receipt-pdf', $payment))->assertForbidden();
});

test('a payment that was never paid has no PDF receipt', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create(), 'decline');

    $this->get($payment->receiptPdfUrl())->assertNotFound();

    $this->get($payment->receiptUrl())->assertDontSee('Download PDF receipt');
});

test('the PDF names the business, the customer, the taxes and the refunds', function () {
    app(Settings::class)->setMany([
        SettingKey::BusinessName->value => 'Maple Studio',
        SettingKey::BusinessAddress->value => '12 King St W, Toronto',
    ]);
    TaxRate::factory()->rate('HST', '13')->create(['registration_number' => '123456789 RT0001']);
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(10000)->taxable()->create(['title' => 'Logo design']));

    app(RefundPayment::class)->handle($payment, Money::of(2000, $payment->currency), 'refund-key', 'Changed scope', User::factory()->admin()->create());

    $html = app(ReceiptPdf::class)->html($payment->refresh());

    expect($html)->toContain('Maple Studio')
        ->toContain('12 King St W, Toronto')
        ->toContain('R-000001')
        ->toContain('Sam Customer')
        ->toContain('Logo design')
        ->toContain('HST (13%)')
        ->toContain('Registration no. 123456789 RT0001')
        ->toContain('CA$113.00')
        ->toContain('-CA$20.00')
        ->toContain('Partially refunded');
});

test('one receipt cannot be rendered over and over', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());
    $url = $payment->receiptPdfUrl();

    foreach (range(1, 10) as $ignored) {
        $this->get($url)->assertSuccessful();
    }

    $this->get($url)->assertTooManyRequests();
});

test('the receipt page offers the PDF once paid', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    $this->get($payment->receiptUrl())
        ->assertSee('Download PDF receipt')
        ->assertSee(e($payment->receiptPdfUrl()), escape: false);
});

test('the receipt email carries the PDF', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    $mail = (new PaymentReceipt($payment))->toMail(new AnonymousNotifiable);

    expect($mail->rawAttachments)->toHaveCount(1)
        ->and($mail->rawAttachments[0]['name'])->toBe('receipt-R-000001-test.pdf')
        ->and($mail->rawAttachments[0]['data'])->toStartWith('%PDF-');
});

test('staff who can view a payment can download its receipt', function (Role $role) {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    $this->actingAs(User::factory()->role($role)->create());

    Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
        ->callAction('downloadReceipt')
        ->assertFileDownloaded('receipt-R-000001-test.pdf');
})->with([Role::Admin, Role::Bookkeeper]);
