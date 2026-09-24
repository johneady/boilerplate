<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Notifications\Payments\WebhookProcessingFailed;
use App\Payments\Actions\ReconcilePayment;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Enums\WebhookEventStatus;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\GatewayUnavailable;
use App\Payments\OpsAlerts;
use App\Payments\PaymentLocator;
use App\Payments\PaymentManager;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Act on a stored webhook event.
 *
 * The event only says that something changed; what changed is read fresh from
 * the gateway by ReconcilePayment. That is what makes duplicate and
 * out-of-order delivery harmless: however many events arrive, in whatever
 * order, each one re-reads the current truth and applies it idempotently.
 *
 * Events for the same payment are processed one at a time (WithoutOverlapping,
 * keyed on the payment), on top of the row lock ReconcilePayment takes.
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
        $payment = $this->payment();

        return [
            (new WithoutOverlapping('payment:'.($payment->id ?? 'event-'.$this->webhookEventId)))
                ->releaseAfter(10)
                ->expireAfter(120),
        ];
    }

    public function handle(PaymentManager $payments, ReconcilePayment $reconcile): void
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);

        if ($event === null || in_array($event->status, [WebhookEventStatus::Processed, WebhookEventStatus::Ignored], true)) {
            return;
        }

        $event->increment('attempts');

        $payment = $this->payment();

        if ($payment === null) {
            $this->finish($event, WebhookEventStatus::Ignored, 'No payment in this application matches the event.');

            return;
        }

        $driver = $payments->webhookDriver($event->gateway, $event->mode);

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
     * The payment the event concerns, or null for an event about nothing
     * this application knows (another integration sharing the gateway
     * account, or an event type it does not act on).
     */
    private function payment(): ?Payment
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);

        if ($event === null) {
            return null;
        }

        $reference = app(PaymentManager::class)->webhookDriver($event->gateway, $event->mode)->paymentReference($event);

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
