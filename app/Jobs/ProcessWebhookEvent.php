<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Notifications\Payments\WebhookProcessingFailed;
use App\Payments\Actions\ReconcilePayment;
use App\Payments\Actions\ReconcileSubscription;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Enums\WebhookEventStatus;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\GatewayUnavailable;
use App\Payments\OpsAlerts;
use App\Payments\PaymentLocator;
use App\Payments\PaymentManager;
use App\Payments\SubscriptionLocator;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Act on a stored webhook event.
 *
 * The event only says that something changed; what changed is read fresh from
 * the gateway by ReconcileSubscription or ReconcilePayment. That is what makes
 * duplicate and out-of-order delivery harmless: however many events arrive,
 * in whatever order, each one re-reads the current truth and applies it
 * idempotently.
 *
 * An event about a subscription (including a renewal invoice) reconciles the
 * subscription, which records its payments; any other event reconciles the
 * payment it names. Events for the same subject are processed one at a time
 * (WithoutOverlapping, keyed on the subject), on top of the row lock the
 * reconcile takes.
 */
class ProcessWebhookEvent extends Job
{
    /**
     * More attempts than the default: a gateway outage while re-reading the
     * payment is exactly the transient failure a webhook should outlast.
     */
    public int $tries = 5;

    public int $maxExceptions = 4;

    public function __construct(public int $webhookEventId) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        $subject = $this->subject();
        $key = match (true) {
            $subject instanceof Subscription => "subscription:{$subject->id}",
            $subject instanceof Payment => "payment:{$subject->id}",
            default => "event-{$this->webhookEventId}",
        };

        return [
            (new WithoutOverlapping($key))
                ->releaseAfter(10)
                ->expireAfter(120),
        ];
    }

    public function handle(PaymentManager $payments, ReconcilePayment $reconcile, ReconcileSubscription $reconcileSubscription): void
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);

        if ($event === null || in_array($event->status, [WebhookEventStatus::Processed, WebhookEventStatus::Ignored], true)) {
            return;
        }

        $event->increment('attempts');

        $subject = $this->subject();

        if ($subject instanceof Subscription) {
            $reconcileSubscription->handle($subject, TransactionSource::Webhook);
            $this->finish($event, WebhookEventStatus::Processed);

            return;
        }

        if (! $subject instanceof Payment) {
            $this->finish($event, WebhookEventStatus::Ignored, 'No payment or subscription in this application matches the event.');

            return;
        }

        $payment = $subject;
        $driver = $payments->webhookDriver($event->gateway, $event->mode);

        if (($refund = $driver->refundInEvent($event, $payment)) !== null) {
            $reconcile->applyRefund($payment, $refund, TransactionSource::Webhook);
        }

        if ($driver->completesCheckout($event) && $payment->status === PaymentStatus::Pending) {
            try {
                $payments->driverFor($payment)->completeCheckout($payment);
            } catch (GatewayUnavailable $e) {
                throw $e;
            } catch (GatewayException $e) {
                // A declined capture leaves the payment pending, and the
                // re-read below records whatever the gateway now says.
                report($e);
            }
        }

        $reconcile->handle($payment, TransactionSource::Webhook);

        $this->finish($event, WebhookEventStatus::Processed);
    }

    public function failed(?Throwable $exception): void
    {
        parent::failed($exception);

        $event = WebhookEvent::query()->find($this->webhookEventId);

        if ($event === null) {
            return;
        }

        $error = $exception?->getMessage() ?? 'Unknown error';
        $this->finish($event, WebhookEventStatus::Failed, $error);

        app(OpsAlerts::class)->send(new WebhookProcessingFailed($event, $error));
    }

    /**
     * The subscription or payment the event concerns, or null for an event
     * about nothing this application knows (another integration sharing the
     * gateway account, or an event type it does not act on).
     *
     * An event naming a subscription never falls back to a payment: a
     * Stripe invoice event for a subscription this application does not
     * know is about nothing here.
     */
    private function subject(): Subscription|Payment|null
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);

        if ($event === null) {
            return null;
        }

        $driver = app(PaymentManager::class)->webhookDriver($event->gateway, $event->mode);

        $subscriptionReference = $driver->subscriptionReference($event);

        if ($subscriptionReference !== null) {
            return $subscriptionReference->isEmpty()
                ? null
                : app(SubscriptionLocator::class)->find($event->gateway, $event->mode, $subscriptionReference);
        }

        $reference = $driver->paymentReference($event);

        if ($reference === null || $reference->isEmpty()) {
            return null;
        }

        return app(PaymentLocator::class)->find($event->gateway, $event->mode, $reference);
    }

    private function finish(WebhookEvent $event, WebhookEventStatus $status, ?string $error = null): void
    {
        $event->forceFill([
            'status' => $status,
            'processed_at' => CarbonImmutable::now(),
            'error' => $error !== null ? mb_substr($error, 0, 2000) : null,
        ])->save();
    }
}
