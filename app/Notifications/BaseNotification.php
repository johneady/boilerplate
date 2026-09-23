<?php

namespace App\Notifications;

use App\Settings\Settings;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base class for the application's notifications.
 *
 * `php artisan make:notification` emits a standalone class, which leaves every
 * notification re-deciding the same three things: which channels it goes to,
 * how it reaches the business name, and how its subject is worded. Extending
 * this settles all three once, the same way App\Jobs\Job does for queued jobs.
 *
 * Named BaseNotification rather than Notification deliberately. A class named
 * App\Notifications\Notification shadows Illuminate's own inside this
 * namespace, so every subclass and every `use` statement in the directory would
 * have to disambiguate the two by hand.
 *
 * Deliberately NOT ShouldQueue. Both notifications in this application are
 * operator alerts sent from a path where queueing is the wrong choice --
 * QueueJobFailed announces that the queue itself just failed, and handing that
 * alert to the same queue is how it ends up unsent in the table it is warning
 * about. A subclass that SHOULD be queued declares `implements ShouldQueue` on
 * itself, which is the explicit decision it deserves to be; see the note on
 * localisation below before doing so.
 */
abstract class BaseNotification extends Notification
{
    /**
     * The delivery channels for this notification.
     *
     * Mail alone, which is every channel this application has configured.
     * Override in a subclass that adds another -- and see .ai/rules for the
     * database/in-app channel decision, which was deliberately deferred.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * The name the application brands itself with.
     *
     * Reads the BusinessName setting rather than config('app.name'), which is
     * the same rule the views and the published mail templates follow: the
     * business name is operator-editable in the admin panel, APP_NAME is not.
     *
     * Resolved per call rather than in a constructor, because a notification
     * may be constructed in one process and rendered in another -- and a
     * subclass that is queued would otherwise serialise a stale name into the
     * payload and mail it out after the setting had changed.
     */
    protected function businessName(): string
    {
        return app(Settings::class)->businessName();
    }

    /**
     * Start a mail message whose subject is suffixed with the business name.
     *
     * The suffix is what makes an alert identifiable in an inbox that receives
     * them from more than one installation of this boilerplate -- "Background
     * job failed" alone does not say whose.
     *
     * Translated through __() so the pattern itself is localisable: languages
     * that order the two parts differently need the whole string, not a
     * concatenation. See .ai/rules/i18n.md.
     */
    protected function mailMessage(string $subject): MailMessage
    {
        return (new MailMessage)->subject(__(':subject on :business', [
            'subject' => $subject,
            'business' => $this->businessName(),
        ]));
    }

    /**
     * Backslash-escape the characters the Markdown parser would act on.
     *
     * The mail template parses every line as Markdown, so a customer-typed
     * value such as "[invoice](https://evil.example)" would otherwise render
     * as a clickable link inside an email the business trusts.
     *
     * The backslash itself first, or the escapes below would double the ones
     * already in the text. Block markers (`#`, `-`, `+`) are included so a
     * line cannot open a heading or a list. A backslash escape renders as the
     * bare character, so nothing legitimate is distorted.
     */
    protected function escapeMarkdownTokens(string $value): string
    {
        $escaped = str_replace('\\', '\\\\', $value);

        foreach (['`', '*', '_', '~', '[', ']', '!', '#', '-', '+'] as $character) {
            $escaped = str_replace($character, '\\'.$character, $escaped);
        }

        return $escaped;
    }

    /**
     * Customer-written text as an escaped Markdown blockquote.
     *
     * The caller wraps the result in HtmlString to keep its line breaks, which
     * opts it out of the mail template's own escaping -- so the text is
     * escaped here for both HTML and Markdown. A stranger wrote it, so
     * escaping it is not optional.
     *
     * Every line is prefixed, blank lines included: a bare `>` on the empty
     * lines is what keeps consecutive paragraphs inside the same quote instead
     * of ending it at the first blank line.
     */
    protected function quotedText(string $text): string
    {
        $lines = preg_split('/\R/', trim($text)) ?: [];

        return implode("\n", array_map(
            fn (string $line): string => rtrim('> '.e($this->escapeMarkdownTokens($line))),
            $lines,
        ));
    }
}
