<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use App\Notifications\Payments\WebhookSignatureRejected;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\WebhookEventStatus;
use App\Payments\Exceptions\InvalidWebhook;
use App\Payments\OpsAlerts;
use App\Payments\PaymentCredentials;
use App\Payments\PaymentManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Receive a webhook from Stripe or PayPal.
 *
 * Verify, store, queue, answer -- and nothing else inside the request. The
 * gateway wants a 2xx within seconds and retries otherwise, so the work is
 * done by ProcessWebhookEvent on the queue. Storing uses insertOrIgnore on
 * (gateway, event_id), so a redelivery is acknowledged and not processed again.
 *
 * The mode is part of the URL (webhooks/stripe/live), which selects the
 * signing secret: each mode's endpoint is registered separately at the
 * gateway, and a sandbox payment's events still verify after an
 * installation switches to live.
 */
class PaymentWebhookController extends Controller
{
    /**
     * Rejections within an hour before operators are told. One bad delivery
     * is noise (a probe, a replay); several means real updates are being lost.
     */
    private const int ALERT_AFTER_REJECTIONS = 5;

    public function __invoke(Request $request, string $gateway, string $mode, PaymentManager $payments, PaymentCredentials $credentials): Response
    {
        $gateway = Gateway::tryFrom($gateway);
        $mode = GatewayMode::tryFrom($mode);

        abort_if($gateway === null || $mode === null || ! $gateway->sendsWebhooks(), 404);

        // An endpoint never set up answers like one that does not exist,
        // whether or not payments are switched on. One that was set up keeps
        // receiving after payments are switched off, so refunds and holds
        // already in flight still complete.
        abort_unless($credentials->hasWebhookSecret($gateway, $mode), 404);

        try {
            $webhook = $payments->webhookDriver($gateway, $mode)->verifyWebhook($request);
        } catch (InvalidWebhook $e) {
            $this->recordRejection($gateway, $e);

            return response('Webhook verification failed.', 400);
        }

        $stored = WebhookEvent::query()->insertOrIgnore([
            'gateway' => $gateway->value,
            'mode' => $mode->value,
            'event_id' => $webhook->eventId,
            'type' => $webhook->type,
            'payload' => json_encode($webhook->payload),
            'status' => WebhookEventStatus::Received->value,
            'attempts' => 0,
            'created_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);

        $event = WebhookEvent::query()
            ->where('gateway', $gateway->value)
            ->where('event_id', $webhook->eventId)
            ->firstOrFail();

        // Dispatched for a new event, and again for a redelivery of one never
        // processed: if the first dispatch failed (the queue was down, so the
        // gateway got a 500 and retried), the redelivery is what recovers it.
        // Processing is idempotent, so a second dispatch of an event still in
        // the queue costs one extra re-read at most.
        if ($stored === 1 || $event->status === WebhookEventStatus::Received) {
            ProcessWebhookEvent::dispatch($event->id);
        }

        return response('OK', 200);
    }

    private function recordRejection(Gateway $gateway, InvalidWebhook $e): void
    {
        Log::warning('Rejected a payment webhook.', ['gateway' => $gateway->value, 'reason' => $e->getMessage()]);

        $key = "payments:webhook-rejections:{$gateway->value}";
        RateLimiter::hit($key, 3600);

        if (RateLimiter::attempts($key) < self::ALERT_AFTER_REJECTIONS) {
            return;
        }

        // At most one alert an hour per gateway, however many follow.
        RateLimiter::attempt(
            "payments:webhook-rejection-alert:{$gateway->value}",
            1,
            fn () => app(OpsAlerts::class)->send(new WebhookSignatureRejected($gateway->label(), $e->getMessage())),
            3600,
        );
    }
}
