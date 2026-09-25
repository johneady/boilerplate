<?php

namespace App\Mail;

use App\Jobs\ProcessUploadedImage;
use App\Models\ContactSubmission;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\ContactSubmissionReceived;
use App\Notifications\PasswordChanged;
use App\Notifications\Payments\AbandonedSubscriptionCanceled;
use App\Notifications\Payments\AuthorizationExpiring;
use App\Notifications\Payments\DisputeOpened;
use App\Notifications\Payments\DuplicatePaymentRefunded;
use App\Notifications\Payments\DuplicateSubscriptionDetected;
use App\Notifications\Payments\PaymentCredentialsChanged;
use App\Notifications\Payments\PaymentReceipt;
use App\Notifications\Payments\RefundIssued;
use App\Notifications\Payments\RefundReversed;
use App\Notifications\Payments\SubscriptionCanceled;
use App\Notifications\Payments\SubscriptionPaymentFailed;
use App\Notifications\Payments\SubscriptionRenewed;
use App\Notifications\Payments\SubscriptionStarted;
use App\Notifications\Payments\TrialEnding;
use App\Notifications\Payments\WebhookProcessingFailed;
use App\Notifications\Payments\WebhookSignatureRejected;
use App\Notifications\QueueJobFailed;
use App\Payments\Enums\BillingInterval;
use App\Payments\Enums\CaptureMethod;
use App\Payments\Enums\Currency;
use App\Payments\Enums\DisputeStatus;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Enums\WebhookEventStatus;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * The catalogue of every email this application can produce.
 *
 * Shared by App\Console\Commands\PreviewMails, which delivers them through a
 * real mailer, and App\Http\Controllers\MailPreviewController, which renders
 * them in the browser. The list lives here so those two cannot drift: an email
 * added for one preview is automatically offered by the other.
 *
 * Adding an email type to the application means adding it to all() below, or
 * it is the one message nobody ever sees rendered before a real recipient does.
 */
class PreviewableEmails
{
    /**
     * The preview address used when no recipient is nominated.
     */
    public const string DEFAULT_RECIPIENT = 'preview@inbox.test';

    /**
     * Every previewable email, keyed by a URL-safe slug.
     *
     * Each entry carries a human description for the listings, the notification
     * or mailable the command sends, and a `render` closure returning the thing
     * that knows how to render itself. The closure exists because toMail() is a
     * convention rather than a method on the base Notification class, so a
     * caller holding only `notification` cannot render it in a typed way --
     * each entry states its own rendering instead.
     *
     * @return array<string, array{description: string, notification: BaseNotification|null, mailable: Mailable|null, onDemand: bool, render: callable(): (Mailable|MailMessage)}>
     */
    public function all(string $recipient = self::DEFAULT_RECIPIENT): array
    {
        $notifiable = $this->notifiable($recipient);

        $testEmail = new TestEmail;
        $verifyEmail = new VerifyEmail;
        $resetPassword = new ResetPassword($this->resetPasswordToken());

        return [
            'test-email' => [
                'description' => 'settings test email',
                'notification' => null,
                'mailable' => $testEmail,
                'onDemand' => false,
                'render' => fn (): Mailable => $testEmail,
            ],
            'verify-email' => [
                'description' => 'email address verification',
                'notification' => $verifyEmail,
                'mailable' => null,
                'onDemand' => false,
                'render' => fn (): MailMessage => $verifyEmail->toMail($notifiable),
            ],
            'reset-password' => [
                'description' => 'password reset',
                'notification' => $resetPassword,
                'mailable' => null,
                'onDemand' => false,
                'render' => fn (): MailMessage => $resetPassword->toMail($notifiable),
            ],
            'contact-submission' => [
                'description' => 'contact form submission',
                // Routed on demand, like the queue alert below: the real
                // message goes to the business address from the settings, which
                // is a bare address with no account behind it.
                //
                // The submission is made, not created: rendering a preview must
                // not leave a row in the contact inbox.
                'notification' => $contactSubmission = new ContactSubmissionReceived(
                    ContactSubmission::factory()->make([
                        'id' => 1,
                        'name' => 'Sam Visitor',
                        'email' => 'sam@example.test',
                        'subject' => 'Question about your widgets',
                        'message' => "Hello,\n\nDo the widgets come in blue? I need about forty of them by the end of the month.\n\nThanks,\nSam",
                    ]),
                ),
                'mailable' => null,
                'onDemand' => true,
                'render' => fn (): MailMessage => $contactSubmission->toMail($notifiable),
            ],
            'password-changed' => [
                'description' => 'password change security alert',
                // Routed to the factory user rather than on demand: unlike the
                // two operator alerts, this one goes to the account holder, so
                // the preview should exercise the same path the real mail takes.
                'notification' => $passwordChanged = new PasswordChanged('203.0.113.42'),
                'mailable' => null,
                'onDemand' => false,
                'render' => fn (): MailMessage => $passwordChanged->toMail($notifiable),
            ],
            // The payment emails. Every model is made in memory, never saved:
            // a preview must leave nothing behind in the payments tables.
            'payment-receipt' => [
                'description' => 'payment receipt',
                'notification' => $paymentReceipt = new PaymentReceipt($payment = $this->samplePayment()),
                'mailable' => null,
                'onDemand' => true,
                'render' => fn (): MailMessage => $paymentReceipt->toMail($notifiable),
            ],
            'refund-issued' => [
                'description' => 'refund confirmation',
                'notification' => $refundIssued = new RefundIssued($this->sampleRefund($payment)),
                'mailable' => null,
                'onDemand' => true,
                'render' => fn (): MailMessage => $refundIssued->toMail($notifiable),
            ],
            'refund-reversed' => [
                'description' => 'refund failed after it was made alert',
                'notification' => $refundReversed = new RefundReversed($this->sampleRefund($payment)->forceFill([
                    'status' => RefundStatus::Failed,
                    'failure_reason' => 'expired_or_canceled_card',
                ])),
                'mailable' => null,
                'onDemand' => true,
                'render' => fn (): MailMessage => $refundReversed->toMail($notifiable),
            ],
            'payment-credentials-changed' => [
                'description' => 'payment credentials changed alert',
                'notification' => $credentialsChanged = new PaymentCredentialsChanged(
                    gateway: 'Stripe',
                    changedFields: ['Live secret key', 'Live webhook signing secret'],
                    changedBy: 'Alex Admin <admin@example.com>',
                    ipAddress: '203.0.113.42',
                ),
                'mailable' => null,
                'onDemand' => true,
                'render' => fn (): MailMessage => $credentialsChanged->toMail($notifiable),
            ],
            'authorization-expiring' => [
                'description' => 'payment hold expiring alert',
                'notification' => $authorizationExpiring = new AuthorizationExpiring($this->samplePayment(PaymentStatus::Authorized)),
                'mailable' => null,
                'onDemand' => true,
                'render' => fn (): MailMessage => $authorizationExpiring->toMail($notifiable),
            ],
            'duplicate-payment-refunded' => [
                'description' => 'duplicate payment refunded alert',
                'notification' => $duplicateRefunded = new DuplicatePaymentRefunded($payment),
                'mailable' => null,
                'onDemand' => true,
                'render' => fn (): MailMessage => $duplicateRefunded->toMail($notifiable),
            ],
            'webhook-processing-failed' => [
                'description' => 'payment webhook failure alert',
                'notification' => $webhookFailed = new WebhookProcessingFailed(
                    (new WebhookEvent)->forceFill([
                        'gateway' => Gateway::Stripe,
                        'mode' => GatewayMode::Live,
                        'event_id' => 'evt_1Pxample0000000000000000',
                        'type' => 'charge.refunded',
                        'payload' => [],
                        'status' => WebhookEventStatus::Failed,
                        'attempts' => 5,
                    ]),
                    'Stripe could not be reached: cURL error 28: Operation timed out',
                ),
                'mailable' => null,
                'onDemand' => true,
                'render' => fn (): MailMessage => $webhookFailed->toMail($notifiable),
            ],
            'webhook-signature-rejected' => [
                'description' => 'payment webhook rejected alert',
                'notification' => $signatureRejected = new WebhookSignatureRejected('PayPal', 'PayPal webhook signature verification failed.'),
                'mailable' => null,
                'onDemand' => true,
                'render' => fn (): MailMessage => $signatureRejected->toMail($notifiable),
            ],
            'subscription-started' => [
                'description' => 'subscription started',
                'notification' => $subscriptionStarted = new SubscriptionStarted($subscription = $this->sampleSubscription()),
                'mailable' => null,
                'onDemand' => false,
                'render' => fn (): MailMessage => $subscriptionStarted->toMail($notifiable),
            ],
            'subscription-renewed' => [
                'description' => 'subscription payment receipt',
                'notification' => $subscriptionRenewed = new SubscriptionRenewed($this->samplePayment()->setRelation('payable', $subscription)),
                'mailable' => null,
                'onDemand' => true,
                'render' => fn (): MailMessage => $subscriptionRenewed->toMail($notifiable),
            ],
            'trial-ending' => [
                'description' => 'free trial ending reminder',
                'notification' => $trialEnding = new TrialEnding($this->sampleSubscription(SubscriptionStatus::Trialing)),
                'mailable' => null,
                'onDemand' => false,
                'render' => fn (): MailMessage => $trialEnding->toMail($notifiable),
            ],
            'subscription-payment-failed' => [
                'description' => 'subscription renewal failed',
                'notification' => $paymentFailed = new SubscriptionPaymentFailed($pastDue = $this->sampleSubscription(SubscriptionStatus::PastDue)),
                'mailable' => null,
                'onDemand' => false,
                'render' => fn (): MailMessage => $paymentFailed->toMail($notifiable),
            ],
            'subscription-payment-failed-ops' => [
                'description' => 'subscription renewal failed alert',
                'notification' => $paymentFailedOps = new SubscriptionPaymentFailed($pastDue, forOperators: true),
                'mailable' => null,
                'onDemand' => true,
                'render' => fn (): MailMessage => $paymentFailedOps->toMail($notifiable),
            ],
            'subscription-canceled' => [
                'description' => 'subscription ended',
                'notification' => $subscriptionCanceled = new SubscriptionCanceled($this->sampleSubscription(SubscriptionStatus::Canceled)),
                'mailable' => null,
                'onDemand' => false,
                'render' => fn (): MailMessage => $subscriptionCanceled->toMail($notifiable),
            ],
            'abandoned-subscription-canceled' => [
                'description' => 'abandoned subscription cancelled alert',
                'notification' => $abandonedCanceled = new AbandonedSubscriptionCanceled($this->sampleSubscription(SubscriptionStatus::Expired)),
                'mailable' => null,
                'onDemand' => true,
                'render' => fn (): MailMessage => $abandonedCanceled->toMail($notifiable),
            ],
            'duplicate-subscription' => [
                'description' => 'duplicate subscription alert',
                'notification' => $duplicateSubscription = new DuplicateSubscriptionDetected($this->sampleSubscription()),
                'mailable' => null,
                'onDemand' => true,
                'render' => fn (): MailMessage => $duplicateSubscription->toMail($notifiable),
            ],
            'dispute-opened' => [
                'description' => 'payment disputed alert',
                'notification' => $disputeOpened = new DisputeOpened((new Dispute)->forceFill([
                    'id' => 1,
                    'payment_id' => 1,
                    'gateway' => Gateway::Stripe,
                    'mode' => GatewayMode::Live,
                    'gateway_dispute_id' => 'dp_1Pxample0000000000000000',
                    'currency' => Currency::CAD,
                    'amount' => 56500,
                    'reason' => 'fraudulent',
                    'status' => DisputeStatus::NeedsResponse,
                    'evidence_due_by' => now()->addDays(7),
                ])->setRelation('payment', $payment)),
                'mailable' => null,
                'onDemand' => true,
                'render' => fn (): MailMessage => $disputeOpened->toMail($notifiable),
            ],
            'queue-failure' => [
                'description' => 'queued job failure alert',
                // Routed on demand rather than to the factory user: the real
                // alert goes to the operations address from the mail settings,
                // which is a bare address with no account behind it.
                'notification' => $queueFailure = new QueueJobFailed(
                    jobName: ProcessUploadedImage::class,
                    connection: 'database',
                    queue: 'default',
                    errorMessage: 'SQLSTATE[HY000] [2002] Connection refused',
                ),
                'mailable' => null,
                'onDemand' => true,
                'render' => fn (): MailMessage => $queueFailure->toMail($notifiable),
            ],
        ];
    }

    /**
     * The slugs of every previewable email.
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys($this->all());
    }

    /**
     * The unpersisted recipient the framework notifications are addressed to.
     *
     * Made rather than created, so the signed verification and reset URLs form
     * exactly as they would for a real recipient while nothing is written to
     * the database.
     */
    public function notifiable(string $recipient = self::DEFAULT_RECIPIENT): User
    {
        return User::factory()->unverified()->make([
            'id' => 1,
            'email' => $recipient,
        ]);
    }

    /**
     * A paid, taxed payment made in memory for the payment emails.
     */
    private function samplePayment(PaymentStatus $status = PaymentStatus::Succeeded): Payment
    {
        return (new Payment)->forceFill([
            'id' => 1,
            'uuid' => '9c3b6f2e-1d4a-4c8b-9e2f-7a5d3c1b0e9f',
            'customer_name' => 'Sam Customer',
            'customer_email' => 'sam@example.test',
            'description' => 'Website design deposit',
            'gateway' => Gateway::Stripe,
            'mode' => GatewayMode::Live,
            'status' => $status,
            'capture_method' => $status === PaymentStatus::Authorized ? CaptureMethod::Manual : CaptureMethod::Automatic,
            'currency' => Currency::CAD,
            'subtotal' => 50000,
            'tax_total' => 6500,
            'amount' => 56500,
            'amount_captured' => $status === PaymentStatus::Authorized ? 0 : 56500,
            'amount_refunded' => 0,
            'tax_lines' => [['name' => 'Sales Tax', 'percentage' => '13.000', 'amount' => 6500]],
            'authorization_expires_at' => now()->addHours(20),
        ]);
    }

    /**
     * A $29/month subscription to a "Pro" plan, made in memory with its
     * relations set, like the sample payment.
     */
    private function sampleSubscription(SubscriptionStatus $status = SubscriptionStatus::Active): Subscription
    {
        $plan = (new Plan)->forceFill(['id' => 1, 'key' => 'pro', 'name' => 'Pro', 'features' => [], 'trial_days' => 14, 'taxable' => true, 'is_active' => true]);
        $price = (new PlanPrice)->forceFill(['id' => 1, 'plan_id' => 1, 'currency' => Currency::CAD, 'amount' => 2900, 'interval' => BillingInterval::Month, 'interval_count' => 1, 'is_active' => true]);

        return (new Subscription)->forceFill([
            'id' => 1,
            'uuid' => '3f6c1a2b-8d4e-4f7a-9b2c-5e1d0a9f8c7b',
            'plan_id' => 1,
            'plan_price_id' => 1,
            'gateway' => Gateway::Stripe,
            'mode' => GatewayMode::Live,
            'status' => $status,
            'currency' => Currency::CAD,
            'trial_ends_at' => $status === SubscriptionStatus::Trialing ? now()->addDays(3) : null,
            'current_period_end' => now()->addMonth(),
            'past_due_since' => $status === SubscriptionStatus::PastDue ? now()->subDay() : null,
        ])->setRelation('plan', $plan)->setRelation('price', $price)->setRelation('user', $this->notifiable());
    }

    /**
     * A partial refund of the sample payment, made in memory.
     */
    private function sampleRefund(Payment $payment): Refund
    {
        return (new Refund)->forceFill([
            'id' => 1,
            'uuid' => '5e8d2c1a-7b3f-4a6e-8d9c-0f1e2d3c4b5a',
            'payment_id' => $payment->id,
            'gateway' => $payment->gateway,
            'currency' => $payment->currency,
            'amount' => 11300,
            'tax_amount' => 1300,
            'status' => RefundStatus::Succeeded,
        ])->setRelation('payment', $payment);
    }

    /**
     * Stand-in for the token the password broker normally issues, built the
     * same way the broker builds a real one: an HMAC-SHA256 of 40 random
     * characters. A literal placeholder such as "preview-token" renders a
     * reset URL a fraction of the real length, so the preview cannot show
     * how the link actually wraps in a mail client.
     */
    private function resetPasswordToken(): string
    {
        return hash_hmac('sha256', Str::random(40), Config::get('app.key'));
    }
}
