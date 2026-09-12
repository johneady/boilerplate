<?php

namespace App\Console\Commands;

use App\Mail\PreviewableEmails;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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
     * Execute the console command.
     */
    public function handle(PreviewableEmails $emails): int
    {
        $recipient = (string) ($this->argument('recipient') ?: PreviewableEmails::DEFAULT_RECIPIENT);

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

        $user = $emails->notifiable($recipient);

        $senders = [];

        foreach ($emails->all($recipient) as $email) {
            $senders[$email['description']] = function () use ($email, $user, $recipient): void {
                if ($email['mailable'] !== null) {
                    Mail::to($recipient)->send($email['mailable']);

                    return;
                }

                if ($email['onDemand']) {
                    Notification::route('mail', $recipient)->notifyNow($email['notification']);

                    return;
                }

                Notification::sendNow($user, $email['notification']);
            };
        }

        $sent = 0;

        foreach ($senders as $description => $send) {
            try {
                $send();
            } catch (Throwable $e) {
                $this->components->error("Failed to send the {$description}: {$e->getMessage()}");

                continue;
            }

            $this->components->info("Sent the {$description}.");
            $sent++;
        }

        $this->components->info("Delivered {$sent} of ".count($senders)." email types to [{$recipient}].");

        return $sent === count($senders) ? self::SUCCESS : self::FAILURE;
    }
}
