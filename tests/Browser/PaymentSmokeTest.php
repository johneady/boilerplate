<?php

use App\Models\PaymentLink;
use App\Models\TaxRate;
use App\Payments\Enums\PaymentStatus;
use Tests\Support\Payments;

/*
 * The whole customer path through the Demo gateway in a real browser: the
 * Livewire pay form, the hosted-checkout redirect and return, and the signed
 * receipt. The feature suite covers every step's server side; this is what
 * catches the page that renders but does nothing when clicked.
 */

test('a customer pays a payment link through the demo checkout and lands on their receipt', function () {
    Payments::enable();
    TaxRate::factory()->rate('HST', '13')->create();
    $link = PaymentLink::factory()->costing(10000)->taxable()->create(['title' => 'Logo design']);

    $page = visit(route('payments.pay', $link, absolute: false));

    $page->assertSee('Logo design')
        ->assertSee('CA$113.00')
        ->fill('name', 'Sam Customer')
        ->fill('email', 'sam@example.test')
        ->click('@pay-button')
        ->assertSee('This is a demonstration checkout')
        ->click('@demo-approve')
        ->assertSee('CA$113.00')
        ->assertPresent('@payment-heading')
        ->assertNoJavaScriptErrors();

    expect($link->payments()->sole()->status)->toBe(PaymentStatus::Succeeded);
});
