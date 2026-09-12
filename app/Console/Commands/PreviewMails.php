<?php

namespace App\Console\Commands;

use App\Jobs\ProcessUploadedImage;
use App\Mail\TestEmail;
use App\Models\User;
use App\Notifications\QueueJobFailed;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Send a preview of every email the application can produce to one address.
 *
 * A design tool rather than a delivery path: point it at a catch-all inbox
 * such as Mailpit (php artisan app:preview-mails --mailer=smtp) and every
 * email type arrives rendered by the real sending pipeline. The framework
 * notifications ride the mail channel to an unpersisted factory user, so
 * the signed verification and reset URLs form exactly as they would for a
 * real recipient while nothing is written to the database; the operational
 * alerts are routed on demand, as they are in production, where they go to
 * an address rather than an account.
 *
 * Adding an email type to the application means adding it to $emails below,
 * or it is the one message nobody ever sees rendered before a real recipient
 * does.
 */
#[Signature('app:preview-mails
    {recipient? : Address to deliver the preview emails to}
    {--mailer= : Send through this mailer instead of the configured default}')]
#[Description('Send a preview of every application email to one address (default preview@inbox.test)')]
class PreviewMails extends Command
{
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

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $recipient = (string) ($this->argument('recipient') ?: 'preview@inbox.test');

        // An explicitly empty --mailer= means "not chosen" (an unset shell
        // variable), not a mailer named "".
        $mailer = $this->option('mailer') ?: null;

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error("[{$recipient}] is not a valid email address.");

            return self::FAILURE;
        }

        if ($mailer !== null && ! array_key_exists($mailer, config('mail.mailers'))) {
            $this->components->error("Mailer [{$mailer}] is not defined in config/mail.php.");

            return self::FAILURE;
        }

        // Resolve the mail manager first so AppServiceProvider's settings
        // override has had its say; only then may an explicit --mailer win.
        app('mail.manager');

        if ($mailer !== null) {
            Config::set('mail.default', $mailer);
        }

        $user = User::factory()->unverified()->make([
            'id' => 1,
            'email' => $recipient,
        ]);

        $emails = [
            'settings test email' => fn () => Mail::to($recipient)->send(new TestEmail),
            'email address verification' => fn () => Notification::sendNow($user, new VerifyEmail),
            'password reset' => fn () => Notification::sendNow($user, new ResetPassword($this->resetPasswordToken())),
            // Routed on demand rather than to the factory user: the real
            // alert goes to the operations address from the mail settings,
            // which is a bare address with no account behind it.
            'queued job failure alert' => fn () => Notification::route('mail', $recipient)
                ->notifyNow(new QueueJobFailed(
                    jobName: ProcessUploadedImage::class,
                    connection: 'database',
                    queue: 'default',
                    errorMessage: 'SQLSTATE[HY000] [2002] Connection refused',
                )),
        ];

        $sent = 0;

        foreach ($emails as $description => $send) {
            try {
                $send();
            } catch (Throwable $e) {
                $this->components->error("Failed to send the {$description}: {$e->getMessage()}");

                continue;
            }

            $this->components->info("Sent the {$description}.");
            $sent++;
        }

        $this->components->info("Delivered {$sent} of ".count($emails)." email types to [{$recipient}].");

        return $sent === count($emails) ? self::SUCCESS : self::FAILURE;
    }
}
