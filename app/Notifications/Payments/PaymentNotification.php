<?php

namespace App\Notifications\Payments;

use App\Notifications\BaseNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

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
}
