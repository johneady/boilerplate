<?php

use App\Mail\PreviewableEmails;
use App\Mail\TestEmail;
use App\Models\User;
use App\Notifications\QueueJobFailed;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Address;

/**
 * How many emails the catalogue holds.
 *
 * Derived rather than written as a literal: these assertions exist to prove the
 * command renders EVERY email, and a hardcoded count turns "somebody added an
 * email type" into a puzzling failure in eight tests at once.
 */
function previewableEmailCount(): int
{
    return count((new PreviewableEmails)->all());
}

/**
 * The command delivers the framework notifications to an unpersisted factory
 * user carrying the preview address. The notification fake matches a
 * notifiable by class and key rather than instance, so assertions can build
 * their own user with the same id.
 */
function previewNotifiable(string $recipient): User
{
    return User::factory()->unverified()->make(['id' => 1, 'email' => $recipient]);
}

/**
 * Whether one of the messages the command just sent addressed the recipient.
 */
function addressedTo(MessageSent $event, string $recipient): bool
{
    return collect($event->message->getTo())
        ->map(fn (Address $address): string => $address->getAddress())
        ->contains($recipient);
}

test('it delivers every email type to the requested address', function () {
    // The real pipeline (the array mailer phpunit.xml configures), with only
    // the sent event intercepted: this is the end-to-end proof that every
    // email type leaves through the mailer addressed to the recipient.
    Event::fake([MessageSent::class]);

    $this->artisan('app:preview-mails', ['recipient' => 'inbox@mailpit.test'])
        ->assertSuccessful();

    Event::assertDispatched(MessageSent::class, previewableEmailCount(), fn (MessageSent $event): bool => addressedTo($event, 'inbox@mailpit.test'));
});

test('it sends each email type without touching the database', function () {
    Mail::fake();
    Notification::fake();

    $this->artisan('app:preview-mails', ['recipient' => 'inbox@mailpit.test'])
        ->assertSuccessful();

    Mail::assertSent(TestEmail::class, fn (TestEmail $mail): bool => $mail->hasTo('inbox@mailpit.test'));

    Notification::assertSentTo(
        previewNotifiable('inbox@mailpit.test'),
        VerifyEmail::class,
        fn (VerifyEmail $notification, array $channels): bool => $channels === ['mail'],
    );

    Notification::assertSentTo(
        previewNotifiable('inbox@mailpit.test'),
        ResetPassword::class,
        fn (ResetPassword $notification, array $channels): bool => $channels === ['mail'],
    );

    // Routed on demand, so it is asserted by address rather than notifiable.
    Notification::assertSentOnDemand(
        QueueJobFailed::class,
        fn (QueueJobFailed $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'inbox@mailpit.test'
            && $channels === ['mail'],
    );

    // The notifiable is a factory-made stand-in: no row may appear for it.
    expect(User::count())->toBe(0);
});

/**
 * The test email is the one message every administrator receives, and its
 * timestamp is a date a person reads: it must render in the display timezone
 * and format settings rather than the UTC wall clock the server keeps.
 */
test('the test email renders its timestamp through the locale and time settings', function () {
    app(Settings::class)->setMany([
        'timezone' => 'Australia/Sydney',
        'date_format' => 'd/m/Y',
        'time_format' => 'H:i',
    ]);

    // Frozen so the assertion pins the UTC-to-Sydney conversion rather than
    // racing the clock: 00:30 UTC is 11:30 AEDT on a January day.
    $this->travelTo(CarbonImmutable::parse('2026-01-15 00:30:00'));

    $this->get('/dev/mails/test-email')
        ->assertSuccessful()
        ->assertSee('15/01/2026, 11:30');
});

test('it falls back to the built-in preview address', function () {
    Event::fake([MessageSent::class]);

    $this->artisan('app:preview-mails')->assertSuccessful();

    Event::assertDispatched(MessageSent::class, previewableEmailCount(), fn (MessageSent $event): bool => addressedTo($event, 'preview@inbox.test'));
});

/**
 * The override has to survive AppServiceProvider's settings callback: that
 * callback rewrites mail.default when the mail manager is first resolved, so
 * a --mailer applied before resolution would silently end up back on the
 * stored choice. Forgetting the manager makes the callback fire inside the
 * command run, against the stored 'log' choice this test sets.
 */
test('an explicit mailer overrides a mailer stored in settings', function () {
    app(Settings::class)->setMany([
        'mail_mailer' => 'log',
        'mail_host' => 'mailpit.example.test',
    ]);

    app()->forgetInstance('mail.manager');

    Event::fake([MessageSent::class]);

    $this->artisan('app:preview-mails', ['--mailer' => 'array'])
        ->assertSuccessful();

    expect(config('mail.default'))->toBe('array');

    Event::assertDispatchedTimes(MessageSent::class, previewableEmailCount());
});

/**
 * The brand in the header and footer of every framework notification comes
 * from the BusinessName setting, not APP_NAME. The two published templates in
 * resources/views/vendor/mail are the only thing making that true, so this
 * renders the real messages and reads the brand back out of the bodies.
 */
test('every email is branded with the business name rather than the app name', function () {
    config(['app.name' => 'Boilerplate']);

    app(Settings::class)->setMany(['business_name' => 'Acme Widgets']);

    $bodies = [];

    Event::listen(function (MessageSent $event) use (&$bodies): void {
        $bodies[] = $event->message->toString();
    });

    $this->artisan('app:preview-mails')->assertSuccessful();

    expect($bodies)->toHaveCount(previewableEmailCount());

    foreach ($bodies as $body) {
        expect($body)->toContain('Acme Widgets')
            ->and($body)->not->toContain('Boilerplate');
    }
});

/**
 * Every email goes out through the same branded Markdown layout, including the
 * settings test email -- an administrator checking their mail configuration
 * should see what their users will receive. Each therefore carries both an
 * HTML and a plain text part rather than text alone.
 */
test('every email is sent as branded HTML with a plain text alternative', function () {
    $messages = [];

    Event::listen(function (MessageSent $event) use (&$messages): void {
        $messages[] = $event->message;
    });

    $this->artisan('app:preview-mails')->assertSuccessful();

    expect($messages)->toHaveCount(previewableEmailCount());

    foreach ($messages as $message) {
        // Asserted as strings: a text-only mailable returns null here, which
        // would otherwise fail as a type error rather than as the missing
        // HTML part it actually is.
        expect((string) $message->getHtmlBody())->toContain('<!DOCTYPE')
            ->and((string) $message->getTextBody())->not->toBeEmpty()
            ->and((string) $message->getTextBody())->not->toContain('<!DOCTYPE');
    }
});

/**
 * The email theme is recoloured to the app's blue accent (see
 * resources/views/vendor/mail/html/themes/default.css). The theme is inlined
 * at send time, so a published theme that stops being found would silently
 * revert every message to the framework's greyscale.
 */
test('every email is themed with the application accent colour', function () {
    $bodies = [];

    Event::listen(function (MessageSent $event) use (&$bodies): void {
        $bodies[] = (string) $event->message->getHtmlBody();
    });

    $this->artisan('app:preview-mails')->assertSuccessful();

    expect($bodies)->toHaveCount(previewableEmailCount());

    foreach ($bodies as $body) {
        // The accent, inlined onto the header band, and the near-black the
        // framework's own theme would have used there instead.
        expect($body)->toContain('#2563eb')
            ->and($body)->not->toContain('background-color: #18181b');
    }
});

/**
 * Every fixed-width table in the message must collapse on a narrow screen, or
 * the email needs sideways scrolling on a phone. The brand band is a 570px
 * table of this application's own (resources/views/vendor/mail/html/header.blade.php),
 * so it has to be named in the layout's media query alongside the framework's
 * .inner-body and .footer -- it was not, and held the message open at full
 * width in Mailpit's mobile view.
 */
test('every fixed width element collapses on a narrow screen', function () {
    $bodies = [];

    Event::listen(function (MessageSent $event) use (&$bodies): void {
        $bodies[] = (string) $event->message->getHtmlBody();
    });

    $this->artisan('app:preview-mails')->assertSuccessful();

    expect($bodies)->toHaveCount(previewableEmailCount());

    foreach ($bodies as $body) {
        // Every class given a 570px width must appear in the narrow-screen
        // media query. Read out of the rendered message rather than listed
        // here, so a new fixed-width table is caught rather than forgotten.
        preg_match_all('/class="([a-z-]+)"[^>]*width="570"/', $body, $matches);

        expect($matches[1])->not->toBeEmpty();

        preg_match('/@media only screen and \(max-width: 600px\)\s*\{(.+?)\}\s*<\/style>/s', $body, $query);

        foreach (array_unique($matches[1]) as $class) {
            expect($query[1] ?? '')->toContain('.'.$class);
        }
    }
});

test('it rejects an invalid recipient address', function () {
    $this->artisan('app:preview-mails', ['recipient' => 'not-an-address'])
        ->expectsOutputToContain('not a valid email address')
        ->assertFailed();
});

test('it rejects a mailer that is not defined', function () {
    $this->artisan('app:preview-mails', ['--mailer' => 'carrier-pigeon'])
        ->expectsOutputToContain('Mailer [carrier-pigeon] is not defined')
        ->assertFailed();
});

/**
 * An undeliverable mailer must report every failure and stop with a non-zero
 * exit code, not crash on the first send.
 */
test('it reports each failed email and exits with a failure code', function () {
    config(['mail.mailers.broken' => ['transport' => 'nonexistent-transport']]);

    $this->artisan('app:preview-mails', ['--mailer' => 'broken'])
        ->expectsOutputToContain('Failed to send the settings test email')
        ->expectsOutputToContain('Failed to send the email address verification')
        ->expectsOutputToContain('Failed to send the password reset')
        ->expectsOutputToContain('Failed to send the queued job failure alert')
        ->expectsOutputToContain('Delivered 0 of '.previewableEmailCount().' email types')
        ->assertFailed();
});

/**
 * The reset link is the whole point of previewing that email, so its token has
 * to be the length a real one is -- the broker issues an HMAC-SHA256 hex digest
 * (64 characters), and a short literal placeholder renders a link that wraps
 * differently in a mail client than the one a real recipient receives.
 */
test('the password reset link carries a token shaped like a real one', function () {
    $bodies = [];

    Event::listen(function (MessageSent $event) use (&$bodies): void {
        $bodies[] = (string) $event->message->getTextBody();
    });

    $this->artisan('app:preview-mails')->assertSuccessful();

    $links = collect($bodies)
        ->flatMap(fn (string $body): array => preg_match('#/reset-password/([^?\s]+)#', $body, $matches) ? [$matches[1]] : [])
        ->all();

    expect($links)->toHaveCount(1)
        ->and($links[0])->toMatch('/^[0-9a-f]{64}$/');
});

/**
 * Random per run rather than a fixed constant, so a preview inbox holding two
 * runs does not show the same link twice and hide a token that never changed.
 */
test('each run issues a different password reset token', function () {
    $tokens = [];

    Notification::fake();

    foreach (range(1, 2) as $ignored) {
        $this->artisan('app:preview-mails')->assertSuccessful();
    }

    Notification::assertSentTo(
        previewNotifiable('preview@inbox.test'),
        ResetPassword::class,
        function (ResetPassword $notification) use (&$tokens): bool {
            $tokens[] = $notification->token;

            return true;
        },
    );

    expect($tokens)->toHaveCount(2)
        ->and($tokens[0])->not->toBe($tokens[1]);
});

/**
 * The command builds its sender list from the catalogue, and must key it by
 * the unique slug rather than the free-text description. Keyed by description,
 * two entries sharing one would collapse into a single sender -- and because
 * the total shrank with it, the command would still report "Delivered N of N"
 * and exit successfully while an email silently never sent.
 */
test('emails sharing a description are all still delivered', function () {
    $duplicated = new class extends PreviewableEmails
    {
        /** @return array<string, array<string, mixed>> */
        public function all(string $recipient = PreviewableEmails::DEFAULT_RECIPIENT): array
        {
            $emails = parent::all($recipient);

            foreach ($emails as $slug => $email) {
                $emails[$slug]['description'] = 'an identically described email';
            }

            return $emails;
        }
    };

    app()->instance(PreviewableEmails::class, $duplicated);

    Event::fake([MessageSent::class]);

    $this->artisan('app:preview-mails')
        ->expectsOutputToContain('Delivered '.previewableEmailCount().' of '.previewableEmailCount().' email types')
        ->assertSuccessful();

    Event::assertDispatchedTimes(MessageSent::class, previewableEmailCount());
});
