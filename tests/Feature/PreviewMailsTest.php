<?php

use App\Mail\TestEmail;
use App\Models\User;
use App\Settings\Settings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Mail\Events\MessageSent;
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
    // the sent event intercepted: this is the end-to-end proof that all three
    // email types leave through the mailer addressed to the recipient.
    Event::fake([MessageSent::class]);

    $this->artisan('app:preview-mails', ['recipient' => 'inbox@mailpit.test'])
        ->assertSuccessful();

    Event::assertDispatched(MessageSent::class, 3, fn (MessageSent $event): bool => addressedTo($event, 'inbox@mailpit.test'));
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

    // The notifiable is a factory-made stand-in: no row may appear for it.
    expect(User::count())->toBe(0);
});

test('it falls back to the built-in preview address', function () {
    Event::fake([MessageSent::class]);

    $this->artisan('app:preview-mails')->assertSuccessful();

    Event::assertDispatched(MessageSent::class, 3, fn (MessageSent $event): bool => addressedTo($event, 'preview@inbox.test'));
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

    Event::assertDispatchedTimes(MessageSent::class, 3);
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
        ->expectsOutputToContain('Delivered 0 of 3 email types')
        ->assertFailed();
});
