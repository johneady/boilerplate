<?php

namespace App\Notifications\Payments;

use App\Models\Payment;
use App\Notifications\BaseNotification;
use App\Payments\ReceiptPdf;
use App\Payments\Tax\TaxLine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Throwable;

/**
 * Base class for every email the payments module sends.
 *
 * Queued, and dispatched only once the surrounding transaction commits: a
 * receipt is sent from inside the work that records a payment, and a
 * rolled-back payment must never have produced a receipt. This is the
 * explicit per-class opt-in to queueing that BaseNotification asks for (see
 * .ai/rules/app-notifications.md) -- the operator alert that reports a dead
 * queue, QueueJobFailed, stays unqueued and is not one of these.
 *
 * The operator alerts here are queued too, deliberately and unlike
 * QueueJobFailed. Each reports a payment condition, not a dead queue: the
 * webhook-failure alert is sent from a worker that is running the failed job's
 * hook, so the queue is demonstrably working, and the one alert that must not
 * wait on a worker -- "the queue is failing" -- is QueueJobFailed, which stays
 * unqueued and covers every job here as well.
 *
 * Exactly-once delivery is decided by the caller, which claims a timestamp
 * (paid_at, receipt_sent_at, notified_at) with a conditional update before
 * sending; models are serialised by id, so a retried send renders what is in
 * the database then.
 */
abstract class PaymentNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    /**
     * Dropped, not failed, when the user it was for has since been deleted:
     * deleting an account cancels its subscription, and the cancellation email
     * queued by that has nobody left to go to.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct()
    {
        $this->afterCommit();
    }

    /**
     * Attach the payment's PDF receipt, when it has one.
     *
     * A receipt that fails to render is reported and left off: the email
     * still carries every figure and the link to the receipt page, and
     * failing the whole send over the attachment would lose both.
     */
    protected function attachReceiptPdf(MailMessage $message, Payment $payment): MailMessage
    {
        if (! ReceiptPdf::availableFor($payment)) {
            return $message;
        }

        $receipt = app(ReceiptPdf::class);

        try {
            $pdf = $receipt->render($payment);
        } catch (Throwable $e) {
            report($e);

            return $message;
        }

        return $message->attachData($pdf, $receipt->filename($payment), ['mime' => 'application/pdf']);
    }

    /**
     * One tax line as an email prints it, with the business's registration
     * number for that tax when it has one.
     */
    protected function taxLineText(TaxLine $line): string
    {
        return $line->registrationNumber === null
            ? __(':tax: :amount', ['tax' => $line->label(), 'amount' => $line->amount->format()])
            : __(':tax: :amount (registration no. :number)', ['tax' => $line->label(), 'amount' => $line->amount->format(), 'number' => $line->registrationNumber]);
    }
}
