<?php

use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\TaxRate;
use App\Notifications\Payments\PaymentNotification;
use App\Notifications\Payments\PaymentReceipt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\Support\Payments;

test('the receipt itemises the tax and links to the signed receipt page', function () {
    Payments::enable();
    TaxRate::factory()->rate('HST', '13')->create();
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(10000)->taxable()->create(['title' => 'Logo design']));

    $mail = (new PaymentReceipt($payment))->toMail(new AnonymousNotifiable);
    $lines = implode("\n", $mail->introLines);

    expect($lines)->toContain('Logo design')
        ->toContain('HST (13%): CA$13.00')
        ->toContain('Total paid: CA$113.00')
        ->and($mail->actionUrl)->toBe($payment->receiptUrl())
        ->and($mail->actionUrl)->toContain('signature=');
});

test('every payment email is queued and held until its transaction commits', function () {
    $classes = collect((new Filesystem)->files(app_path('Notifications/Payments')))
        ->map(fn ($file): string => 'App\\Notifications\\Payments\\'.$file->getFilenameWithoutExtension())
        ->reject(fn (string $class): bool => $class === PaymentNotification::class);

    expect($classes)->not->toBeEmpty();

    foreach ($classes as $class) {
        expect(is_subclass_of($class, PaymentNotification::class))->toBeTrue("{$class} does not extend PaymentNotification.")
            ->and(is_subclass_of($class, ShouldQueue::class))->toBeTrue();
    }

    $receipt = new PaymentReceipt(Payment::factory()->make());

    expect($receipt->afterCommit)->toBeTrue();
});
