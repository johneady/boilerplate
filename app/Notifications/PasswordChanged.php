<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells a user that their account password has just changed.
 *
 * This is the notification every application needs and few ship: it is the
 * only thing that turns a silent account takeover into one the owner finds out
 * about. An attacker who has the password changes it; without this mail the
 * legitimate owner discovers it the next time they try to sign in, by which
 * point the attacker has had uninterrupted access.
 *
 * It is sent from a model event on the password attribute (see
 * App\Providers\AppServiceProvider), so BOTH ways a password can change --
 * the signed-in Settings -> Security form and Fortify's forgotten-password
 * reset -- are covered by one hook rather than a call that a third path could
 * forget to make.
 *
 * Queued, unlike the two operator alerts in this directory. Those announce
 * that something is already broken and must not depend on a worker; this one
 * is ordinary user mail on a path where an SMTP round trip would otherwise be
 * charged to the request that changed the password.
 *
 * Deliberately contains no link and no token. A security alert that invites
 * the reader to click something trains exactly the reflex that phishing
 * depends on, so this one tells them where to go and lets them navigate there
 * themselves.
 */
class PasswordChanged extends BaseNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ?string $changedAtIp = null) {}

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->mailMessage(__('Your password was changed'))
            ->greeting(__('Your password was changed'))
            ->line(__('The password on your :business account was just changed.', [
                'business' => $this->businessName(),
            ]));

        // The IP is included only when the caller knows it -- a password reset
        // run from an artisan command or a test has no request behind it, and
        // an invented value would be worse than none in a security alert.
        if ($this->changedAtIp !== null) {
            $message->line(__('Request address: :ip', ['ip' => $this->changedAtIp]));
        }

        return $message
            ->line(__('You were signed out everywhere else as part of this change, so any other session will need to sign in again.'))
            ->line(__('If you made this change, nothing more is needed.'))
            // No action button and no link, deliberately: see the class note.
            ->line(__('If you did NOT make this change, someone else may have access to your account. Go to the site yourself -- do not follow a link in this email -- and reset your password immediately.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array{ip: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return ['ip' => $this->changedAtIp];
    }
}
