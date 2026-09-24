<?php

use App\Filament\Resources\PaymentLinks\Pages\ManagePaymentLinks;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\TaxRates\Pages\ManageTaxRates;
use App\Filament\Resources\WebhookEvents\Pages\ListWebhookEvents;
use App\Jobs\ProcessWebhookEvent;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Payments\Enums\Currency;
use App\Payments\Enums\PaymentLinkAmountType;
use App\Payments\Enums\PaymentLinkUsage;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;
use App\Payments\Enums\WebhookEventStatus;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Fixtures\Payments\HeldBooking;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
    $this->admin = User::factory()->admin()->create();
});

test('the payment screens are reachable by an administrator', function (string $path) {
    $this->actingAs($this->admin)->get($path)->assertSuccessful();
})->with(['/admin/payments', '/admin/payment-links', '/admin/tax-rates', '/admin/webhook-events']);

test('the payment screens are closed to ordinary users', function (string $path) {
    $this->actingAs(User::factory()->create())->get($path)->assertForbidden();
})->with(['/admin/payments', '/admin/payment-links', '/admin/tax-rates', '/admin/webhook-events']);

test('the payment screens disappear while payments are switched off', function (string $path) {
    Payments::enable(['payments_enabled' => false]);

    $this->actingAs($this->admin)->get($path)->assertForbidden();
})->with(['/admin/payments', '/admin/payment-links', '/admin/tax-rates', '/admin/webhook-events']);

test('the payments list shows the current mode\'s payments by default', function () {
    $sandbox = Payment::factory()->create();
    $live = Payment::factory()->live()->create();

    $this->actingAs($this->admin);

    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$sandbox])
        ->assertCanNotSeeTableRecords([$live]);
});

test('an administrator can refund part of a payment from its page', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());

    $this->actingAs($this->admin);

    Livewire::test(ViewPayment::class, ['record' => $payment->uuid])
        ->callAction('refund', ['amount' => '12.50', 'reason' => 'Late delivery'])
        ->assertHasNoActionErrors();

    expect($payment->refunds()->sole())
        ->amount->toBe(1250)
        ->status->toBe(RefundStatus::Succeeded)
        ->initiated_by->toBe($this->admin->id)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::PartiallyRefunded);
});

test('the refund modal reports an amount over what is refundable instead of refunding', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());

    $this->actingAs($this->admin);

    Livewire::test(ViewPayment::class, ['record' => $payment->uuid])
        ->callAction('refund', ['amount' => '50.01'])
        ->assertNotified(__('payments.actions.refund_failed'));

    expect($payment->refunds()->count())->toBe(0);
});

test('refund is offered only on a paid payment, capture and void only on a hold', function () {
    HeldBooking::createTable();
    $paid = Payments::payWithDemo(PaymentLink::factory()->create());
    $held = Payments::payWithDemo(HeldBooking::query()->create(['price' => 5000]));

    $this->actingAs($this->admin);

    Livewire::test(ListPayments::class)
        ->assertTableActionVisible('refund', $paid)
        ->assertTableActionHidden('capture', $paid)
        ->assertTableActionHidden('refund', $held)
        ->assertTableActionVisible('capture', $held)
        ->assertTableActionVisible('void', $held);
});

test('an administrator can capture a hold from the list', function () {
    HeldBooking::createTable();
    $held = Payments::payWithDemo(HeldBooking::query()->create(['price' => 5000]));

    $this->actingAs($this->admin);

    Livewire::test(ListPayments::class)->callTableAction('capture', $held, ['amount' => '50.00']);

    expect($held->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

test('a payment link is created in the installation currency, priced in cents, owned by its creator', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManagePaymentLinks::class)
        ->callAction('create', [
            'title' => 'Website deposit',
            'amount_type' => PaymentLinkAmountType::Fixed->value,
            'amount' => '250.00',
            'usage' => PaymentLinkUsage::SingleUse->value,
            'taxable' => true,
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    expect(PaymentLink::sole())
        ->amount->toBe(25000)
        ->currency->toBe(Currency::CAD)
        ->created_by->toBe($this->admin->id)
        ->token->toHaveLength(32);
});

test('a payment link must cost something', function (array $amounts, string $field) {
    $this->actingAs($this->admin);

    Livewire::test(ManagePaymentLinks::class)
        ->callAction('create', [
            'title' => 'Broken',
            'usage' => PaymentLinkUsage::Reusable->value,
            ...$amounts,
        ])
        ->assertHasActionErrors([$field]);

    expect(PaymentLink::count())->toBe(0);
})->with([
    'a zero price' => [['amount_type' => 'fixed', 'amount' => '0'], 'amount'],
    'a zero minimum' => [['amount_type' => 'customer_entered', 'min_amount' => '0.00'], 'min_amount'],
    'a maximum below the minimum' => [['amount_type' => 'customer_entered', 'min_amount' => '50', 'max_amount' => '10'], 'max_amount'],
]);

test('a payment link that has taken money cannot be deleted from the panel', function () {
    $paid = PaymentLink::factory()->create();
    Payments::payWithDemo($paid);
    $unpaid = PaymentLink::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(ManagePaymentLinks::class)
        ->assertTableActionHidden(DeleteAction::class, $paid)
        ->callTableAction(DeleteAction::class, $unpaid);

    expect(PaymentLink::query()->pluck('id')->all())->toBe([$paid->id]);
});

test('an administrator can record a payment received by e-Transfer against a link', function () {
    $link = PaymentLink::factory()->costing(8000)->create();

    $this->actingAs($this->admin);

    Livewire::test(ManagePaymentLinks::class)
        ->callTableAction('recordManualPayment', $link, [
            'amount' => '80.00',
            'method' => 'e_transfer',
            'reference' => 'CAx9',
            'received_on' => now()->toDateString(),
            'customer_name' => 'Pat Payer',
            'customer_email' => 'pat@example.test',
        ])
        ->assertHasNoActionErrors();

    expect($link->payments()->sole())
        ->status->toBe(PaymentStatus::Succeeded)
        ->amount->toBe(8000)
        ->recorded_by->toBe($this->admin->id);
});

test('a tax rate keeps its three decimal places', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManageTaxRates::class)
        ->callAction('create', ['name' => 'QST', 'percentage' => '9.975', 'is_active' => true])
        ->assertHasNoActionErrors();

    expect(TaxRate::sole()->percentage)->toBe('9.975');
});

test('a tax rate over 100% is refused', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManageTaxRates::class)
        ->callAction('create', ['name' => 'Typo', 'percentage' => '130', 'is_active' => true])
        ->assertHasActionErrors(['percentage']);
});

test('a failed webhook event can be retried', function () {
    Queue::fake([ProcessWebhookEvent::class]);
    $event = WebhookEvent::factory()->failed()->create();

    $this->actingAs($this->admin);

    Livewire::test(ListWebhookEvents::class)->callTableAction('retry', $event);

    expect($event->fresh()->status)->toBe(WebhookEventStatus::Received);
    Queue::assertPushed(ProcessWebhookEvent::class, fn (ProcessWebhookEvent $job): bool => $job->webhookEventId === $event->id);
});
