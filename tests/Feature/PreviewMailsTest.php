<?php

use App\Mail\TestEmail;
use App\Models\User;
use App\Notifications\QueueJobFailed;
use App\Settings\Settings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Address;

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

    Event::assertDispatched(MessageSent::class, 4, fn (MessageSent $event): bool => addressedTo($event, 'inbox@mailpit.test'));
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

test('it falls back to the built-in preview address', function () {
    Event::fake([MessageSent::class]);

    $this->artisan('app:preview-mails')->assertSuccessful();

    Event::assertDispatched(MessageSent::class, 4, fn (MessageSent $event): bool => addressedTo($event, 'preview@inbox.test'));
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

    Event::assertDispatchedTimes(MessageSent::class, 4);
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

    expect($bodies)->toHaveCount(4);

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

    expect($messages)->toHaveCount(4);

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

    expect($bodies)->toHaveCount(4);

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

    expect($bodies)->toHaveCount(4);

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
        ->expectsOutputToContain('Delivered 0 of 4 email types')
        ->assertFailed();
});
