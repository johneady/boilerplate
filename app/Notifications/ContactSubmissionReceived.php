<?php

namespace App\Notifications;

use App\Models\ContactSubmission;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

/**
 * Tells the business that somebody used the public contact form.
 *
 * Deliberately NOT a ShouldQueue notification, for the same reason as
 * QueueJobFailed: the submission is already safely in the database by the time
 * this is sent, and the send is wrapped in a try/catch by the caller, so the
 * only thing queueing would add is a second way for the message to go missing
 * on an instance whose worker is not running. A contact form is also low enough
 * volume that one inline send costs nothing worth optimising.
 *
 * The reply-to is the submitter's address, so answering the notification
 * answers the person -- the from address stays the application's, because
 * sending as an unverified visitor address is how mail gets marked as spoofed.
 */
class ContactSubmissionReceived extends BaseNotification
{
    public function __construct(private readonly ContactSubmission $submission) {}

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $businessName = $this->businessName();

        $subject = $this->submission->subject;

        $message = $this->mailMessage(__('New contact form message'))
            ->greeting(__('New contact form message'))
            ->replyTo($this->submission->email, $this->submission->name)
            ->line(__('Somebody sent this through the contact form on :business.', ['business' => $businessName]));

        // Name, address and subject are the visitor's own strings, and the
        // mail template parses every line as Markdown after escaping its
        // HTML -- so a name of "[invoice](https://evil.example)" would render
        // as a clickable link inside a notification the business trusts, the
        // same attack as the quoted message below. Markdown-escaped here;
        // the template still applies the HTML escaping for a plain string.
        $message->line(__('From: :name (:email)', [
            'name' => $this->escapeMarkdownTokens($this->submission->name),
            'email' => $this->escapeMarkdownTokens($this->submission->email),
        ]));

        if (filled($subject)) {
            $message->line(__('Subject: :subject', [
                'subject' => $this->escapeMarkdownTokens($subject),
            ]));
        }

        // Wrapped in HtmlString so the line survives as several lines. A plain
        // string handed to line() is collapsed to one run of words -- the same
        // trap QueueJobFailed's table hit -- which would print a multi-paragraph
        // message as a single unbroken paragraph.
        //
        // That makes this the one line not escaped by the mail template, so the
        // visitor's own words are escaped here instead, before the blockquote
        // prefix is applied. Skipping that would let a stranger put HTML into an
        // email the business reads.
        $message->line(__('Their message:'));
        $message->line(new HtmlString($this->quotedMessage()));

        return $message->line(__('Reply to this email to answer them directly. The message is also saved in the admin panel.'));
    }

    /**
     * The submitted message as an escaped Markdown blockquote.
     *
     * Escaped here rather than by the mail template, because toMail() passes
     * this through HtmlString to keep its line breaks -- which opts it out of
     * the escaping every other line gets. A stranger wrote this text, so
     * escaping it is not optional.
     *
     * Every line is prefixed, blank lines included: a bare `>` on the empty
     * lines is what keeps consecutive paragraphs inside the same quote instead
     * of ending it at the first blank line.
     */
    private function quotedMessage(): string
    {
        $lines = preg_split('/\R/', trim($this->submission->message)) ?: [];

        return implode("\n", array_map(
            fn (string $line): string => rtrim('> '.$this->escapeMarkdown($line)),
            $lines,
        ));
    }

    /**
     * Escape a line for both HTML and the Markdown parser.
     *
     * e() covers the HTML the mail template would otherwise escape anyway;
     * backslash-escaping the Markdown-significant characters covers what it
     * does not -- `[text](url)` and `*emphasis*` are not HTML, so without this
     * a visitor's message renders as a clickable link inside a notification
     * the business has every reason to trust.
     */
    private function escapeMarkdown(string $line): string
    {
        return e($this->escapeMarkdownTokens($line));
    }

    /**
     * Backslash-escape the characters the Markdown parser would act on.
     *
     * The backslash itself first, or the escapes below would double the ones
     * already in the visitor's text. Block markers (`#`, `-`, `+`) are
     * included so a line cannot open a heading or a list inside the quote.
     * A backslash escape renders as the bare character, so nothing legitimate
     * is distorted.
     */
    private function escapeMarkdownTokens(string $value): string
    {
        $escaped = str_replace('\\', '\\\\', $value);

        foreach (['`', '*', '_', '~', '[', ']', '!', '#', '-', '+'] as $character) {
            $escaped = str_replace($character, '\\'.$character, $escaped);
        }

        return $escaped;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array{id: int, name: string, email: string, subject: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'id' => $this->submission->id,
            'name' => $this->submission->name,
            'email' => $this->submission->email,
            'subject' => $this->submission->subject,
        ];
    }
}
