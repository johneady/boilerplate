<?php

namespace App\Http\Controllers;

use App\Mail\PreviewableEmails;
use Illuminate\Http\Response;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Renders every application email in the browser for design review.
 *
 * The companion to app:preview-mails: the command proves an email survives the
 * real sending pipeline, while this renders the same catalogue instantly, with
 * no SMTP round trip, so a template change can be reloaded rather than resent.
 * Both read App\Mail\PreviewableEmails, so neither can offer an email the
 * other does not.
 *
 * Registered only in local and testing (routes/web.php), and deliberately NOT
 * behind DevLoginAccounts::enabled() like the error-page previews: that gate is
 * a denylist of production alone, so it is ON for staging and every bespoke
 * environment name. These messages render signed verification and reset URLs
 * for a stand-in account, so the safe environments are named explicitly --
 * the approach .ai/rules/dev-login.md prescribes for data-exposing behaviour.
 */
class MailPreviewController extends Controller
{
    /**
     * Show the index of previewable emails, or render one of them.
     */
    public function __invoke(PreviewableEmails $emails, ?string $slug = null): Response
    {
        $catalogue = $emails->all();

        if ($slug === null) {
            return response()->view('mail.preview-index', [
                'emails' => $catalogue,
            ]);
        }

        abort_unless(array_key_exists($slug, $catalogue), 404);

        return response($this->render($catalogue[$slug]))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Render one email to its HTML body.
     *
     * Each catalogue entry supplies its own renderer, so this uses the
     * framework's own Mailable::render() and MailMessage::render() rather than
     * a copy of MailChannel's view assembly -- the preview cannot drift from
     * what the real pipeline builds. Nothing is handed to a transport, so this
     * costs no delivery and cannot escape to a real inbox even when the stored
     * mail settings point at a live SMTP server.
     *
     * @param  array{description: string, render: callable(): (Mailable|MailMessage)}  $email
     */
    private function render(array $email): string
    {
        return (string) ($email['render'])()->render();
    }
}
