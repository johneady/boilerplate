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
     */
    public function content(): Content
    {
        return new Content(
            text: 'mail.test-email',
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
