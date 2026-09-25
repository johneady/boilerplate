<?php

use App\Models\PaymentLink;
use App\Models\TaxRate;
use App\Notifications\Payments\PaymentReceipt;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
});

test('each tax line is snapshotted with its rate\'s registration number', function () {
    TaxRate::factory()->rate('GST', '5')->create(['registration_number' => '123456789 RT0001']);
    TaxRate::factory()->rate('QST', '9.975')->create(['registration_number' => '1234567890 TQ0001']);

    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(10000)->taxable()->create());

    expect(array_column($payment->tax_lines, 'registration_number', 'name'))
        ->toBe(['GST' => '123456789 RT0001', 'QST' => '1234567890 TQ0001']);
});

test('a later change to the registration number leaves past receipts alone', function () {
    $rate = TaxRate::factory()->rate('GST', '5')->create(['registration_number' => '123456789 RT0001']);
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(10000)->taxable()->create());

    $rate->update(['registration_number' => '987654321 RT0001']);

    expect($payment->refresh()->taxLines()[0]->registrationNumber)->toBe('123456789 RT0001');
});

test('a rate with no registration number snapshots exactly as before', function () {
    // The key is omitted rather than stored as null, so a line without one is
    // byte-for-byte what every earlier receipt holds.
    TaxRate::factory()->rate('HST', '13')->create();

    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(10000)->taxable()->create());

    expect($payment->tax_lines[0])->not->toHaveKey('registration_number');
});

test('the receipt page and email print the registration number beside its tax', function () {
    TaxRate::factory()->rate('GST', '5')->create(['registration_number' => '123456789 RT0001']);
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(10000)->taxable()->create());

    $this->get($payment->receiptUrl())
        ->assertSeeInOrder(['GST (5%)', 'Registration no. 123456789 RT0001']);

    $mail = (new PaymentReceipt($payment))->toMail(new AnonymousNotifiable);

    expect(implode("\n", $mail->introLines))
        ->toContain('GST (5%): CA$5.00 (registration no. 123456789 RT0001)');
});

test('a PayPal plan\'s combined tax carries the registration numbers of the rates it charges', function () {
    TaxRate::factory()->rate('GST', '5')->create(['registration_number' => '123456789 RT0001']);
    TaxRate::factory()->rate('QST', '9.975')->create(['registration_number' => '1234567890 TQ0001']);
    TaxRate::factory()->rate('Levy', '1')->create();
    // Active now, but not part of the plan's recorded tax: it must not appear.
    TaxRate::factory()->rate('PST', '7')->create(['registration_number' => 'PST-999']);

    expect(TaxRate::registrationNumbersFor('GST + QST + Levy'))
        ->toBe('123456789 RT0001 / 1234567890 TQ0001')
        ->and(TaxRate::registrationNumbersFor('Levy'))->toBeNull();
});

test('a blank registration number is stored as none', function () {
    $rate = TaxRate::factory()->create(['registration_number' => '   ']);

    expect($rate->registration_number)->toBeNull()
        ->and($rate->toCalculatorRate()['registration_number'])->toBeNull();
});
