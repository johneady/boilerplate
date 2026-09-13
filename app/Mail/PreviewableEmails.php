<?php

namespace App\Mail;

use App\Jobs\ProcessUploadedImage;
use App\Models\ContactSubmission;
use App\Models\User;
use App\Notifications\ContactSubmissionReceived;
use App\Notifications\QueueJobFailed;
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
