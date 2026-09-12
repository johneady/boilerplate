<?php

namespace App\Mail;

use App\Settings\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The message the settings page sends itself to prove the mail settings work.
 *
 * Sent from the administrator's own action rather than any application flow,
 * so it has no failure consequences and can be sent to any address.
 */
class TestEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $businessName = app(Settings::class)->businessName();

        return new Envelope(
            subject: __('Test email from :business', ['business' => $businessName]),
        );
    }

    /**
     * Get the message content definition.
     *
     * Markdown rather than a plain text view, so this renders through the same
     * branded layout as the framework notifications the settings page sits
     * alongside -- an administrator checking their mail configuration should
     * see what their users will receive, not a bare text message. Laravel
     * builds the plain text part from the same template automatically.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.test-email',
            with: [
                'businessName' => app(Settings::class)->businessName(),
                // Named for the administrator reading it: which transport
                // actually carried this message is the whole point of the
                // test, and "log" arriving here is the usual explanation for
                // "the test passed but nobody received anything".
                'mailer' => (string) config('mail.default'),
                'sentAt' => now()->toDayDateTimeString(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
