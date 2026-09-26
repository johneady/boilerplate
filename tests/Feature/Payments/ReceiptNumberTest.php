<?php

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\PaymentLink;
use App\Models\User;
use App\Notifications\Payments\PaymentReceipt;
use App\Payments\Actions\ReconcilePayment;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\TransactionSource;
use App\Payments\ReceiptNumbers;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
});

test('paid payments are numbered in the order they are paid', function () {
    $first = Payments::payWithDemo(PaymentLink::factory()->create());
    $second = Payments::payWithDemo(PaymentLink::factory()->create());

    expect($first->receipt_number)->toBe(1)
        ->and($second->receipt_number)->toBe(2)
        ->and($second->receiptNumber())->toBe('R-000002');
});

test('a payment that is never paid takes no number', function () {
    $declined = Payments::payWithDemo(PaymentLink::factory()->create(), 'decline');
    $paid = Payments::payWithDemo(PaymentLink::factory()->create());

    // The declined checkout left no gap in the sequence.
    expect($declined->receipt_number)->toBeNull()
        ->and($paid->receipt_number)->toBe(1);
});

test('reconciling a paid payment again keeps its number', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    app(ReconcilePayment::class)->handle($payment, TransactionSource::Scheduler);

    expect($payment->refresh()->receipt_number)->toBe(1);
});

test('a number taken by a transaction that rolls back is handed out again', function () {
    $numbers = app(ReceiptNumbers::class);

    try {
        DB::transaction(function () use ($numbers): void {
            $numbers->next(GatewayMode::Sandbox);

            throw new RuntimeException('The payment failed to save.');
        });
    } catch (RuntimeException) {
        // Expected: the rollback is what is under test.
    }

    expect(DB::transaction(fn (): int => $numbers->next(GatewayMode::Sandbox)))->toBe(1);
});

test('sandbox and live payments are numbered in separate series', function () {
    // A test payment taken between two live sales must not leave a hole in
    // the live series an accountant reads.
    $numbers = app(ReceiptNumbers::class);

    $taken = DB::transaction(fn (): array => [
        $numbers->next(GatewayMode::Live),
        $numbers->next(GatewayMode::Sandbox),
        $numbers->next(GatewayMode::Live),
    ]);

    expect($taken)->toBe([1, 1, 2]);
});

test('a lost counter carries on from the highest number already issued', function () {
    Payments::payWithDemo(PaymentLink::factory()->create());
    Payments::payWithDemo(PaymentLink::factory()->create());

    DB::table('sequences')->where('name', 'receipt:sandbox')->delete();

    $next = Payments::payWithDemo(PaymentLink::factory()->create());

    expect($next->receipt_number)->toBe(3);
});

test('the receipt page and email show the receipt number', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    $this->get($payment->receiptUrl())->assertSee('R-000001');

    $mail = (new PaymentReceipt($payment))->toMail(new AnonymousNotifiable);

    expect(implode("\n", $mail->introLines))->toContain('Receipt number: R-000001');
});

test('the payments list finds a payment by its receipt number however it is typed', function (string $search) {
    $wanted = Payments::payWithDemo(PaymentLink::factory()->create());
    $other = Payments::payWithDemo(PaymentLink::factory()->create());

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListPayments::class)
        ->searchTable($search)
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$other]);
})->with(['R-000001', 'r-000001', '000001']);

test('a search that merely contains digits does not match a receipt', function () {
    $first = Payments::payWithDemo(PaymentLink::factory()->create());

    $this->actingAs(User::factory()->admin()->create());

    // The address holds a 1, but it is not a receipt number.
    Livewire::test(ListPayments::class)
        ->searchTable('jane1@example.com')
        ->assertCanNotSeeTableRecords([$first]);
});
