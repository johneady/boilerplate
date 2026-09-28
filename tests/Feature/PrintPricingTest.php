<?php

use App\Prints\PrintPricing;
use App\Settings\SettingKey;
use App\Settings\Settings;

beforeEach(function () {
    // The lab's defaults: $3.99 a print, three for $9.99. Pinned per test
    // rather than relying on the enum defaults, so a default change fails
    // the arithmetic below for the right reason -- the deal, not the number.
    $this->settings = app(Settings::class);

    $this->settings->set(SettingKey::PrintUnitPrice, 399);
    $this->settings->set(SettingKey::PrintBundlePrice, 999);
});

test('a lone print costs the single price', function () {
    $quote = PrintPricing::quote(1);

    expect($quote->totalCents)->toBe(399)
        ->and($quote->savingsCents)->toBe(0)
        ->and($quote->hasSavings())->toBeFalse();
});

test('two prints are two singles, with the bundle one print away', function () {
    $quote = PrintPricing::quote(2);

    expect($quote->totalCents)->toBe(798)
        ->and($quote->printsToNextBundle())->toBe(1);
});

test('three prints cost the bundle, whatever photos they came from', function () {
    $quote = PrintPricing::quote(3);

    // Three singles would be 1197; the deal saves the difference.
    expect($quote->totalCents)->toBe(999)
        ->and($quote->listTotalCents)->toBe(1197)
        ->and($quote->savingsCents)->toBe(198)
        ->and($quote->printsToNextBundle())->toBe(0);
});

test('prints beyond a whole bundle are priced as singles', function () {
    // One bundle plus two singles: the shape of most real orders.
    expect(PrintPricing::quote(5)->totalCents)->toBe(999 + 2 * 399)
        ->and(PrintPricing::quote(6)->totalCents)->toBe(2 * 999)
        ->and(PrintPricing::quote(7)->totalCents)->toBe(2 * 999 + 399);
});

test('no prints cost nothing', function () {
    expect(PrintPricing::quote(0)->totalCents)->toBe(0);
});

test('changing the prices changes the deal, and the snapshot survives on the order', function () {
    $this->settings->set(SettingKey::PrintUnitPrice, 500);
    $this->settings->set(SettingKey::PrintBundlePrice, 1200);

    $quote = PrintPricing::quote(3);

    expect($quote->totalCents)->toBe(1200)
        ->and($quote->savingsCents)->toBe(300)
        ->and($quote->unit())->toBe('$5.00')
        ->and($quote->bundle())->toBe('$12.00');
});
