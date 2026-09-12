<?php

use App\Mail\PreviewableEmails;
use App\Models\User;
use App\Settings\Settings;

test('the index lists every previewable email', function () {
    $response = $this->get('/dev/mails')->assertSuccessful();

    foreach (app(PreviewableEmails::class)->all() as $slug => $email) {
        $response->assertSee(route('dev.mails.show', $slug), false)
            ->assertSee(Str::ucfirst($email['description']));
    }
});

test('every email renders in the browser', function (string $slug) {
    $html = (string) $this->get('/dev/mails/'.$slug)
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->getContent();

    expect($html)->toContain('<!DOCTYPE');
})->with(['test-email', 'verify-email', 'reset-password', 'queue-failure']);

test('an unknown email slug is not found', function () {
    $this->get('/dev/mails/carrier-pigeon')->assertNotFound();
});

/**
 * The whole point of rendering rather than sending: a preview must not reach a
 * transport, so it cannot escape to a real inbox even while the stored mail
 * settings point at a live SMTP server.
 */
test('rendering a preview sends no mail', function () {
    Mail::fake();
    Notification::fake();

    foreach (app(PreviewableEmails::class)->slugs() as $slug) {
        $this->get('/dev/mails/'.$slug)->assertSuccessful();
    }

    Mail::assertNothingSent();
    Notification::assertNothingSent();
});

/**
 * Rendered previews must brand from the BusinessName setting like the sent
 * messages do -- otherwise the preview shows a brand no recipient ever sees.
 */
test('previews are branded with the business name rather than the app name', function () {
    config(['app.name' => 'Boilerplate']);

    app(Settings::class)->setMany(['business_name' => 'Acme Widgets']);

    foreach (app(PreviewableEmails::class)->slugs() as $slug) {
        $html = (string) $this->get('/dev/mails/'.$slug)->assertSuccessful()->getContent();

        expect($html)->toContain('Acme Widgets')
            ->and($html)->not->toContain('Boilerplate');
    }
});

/**
 * The preview renders the signed URLs a real recipient would follow, so the
 * reset token has to be the length the broker actually issues rather than a
 * short placeholder that wraps differently.
 */
test('the password reset preview carries a realistic token', function () {
    $html = (string) $this->get('/dev/mails/reset-password')->assertSuccessful()->getContent();

    expect($html)->toMatch('#/reset-password/[0-9a-f]{64}#');
});

/**
 * The stand-in notifiable is made, never created: a preview must not leave a
 * row behind.
 */
test('rendering a preview writes nothing to the database', function () {
    foreach (app(PreviewableEmails::class)->slugs() as $slug) {
        $this->get('/dev/mails/'.$slug)->assertSuccessful();
    }

    expect(User::count())->toBe(0);
});

/**
 * Unlike the error-page previews, these are gated on the safe environments by
 * name rather than on DevLoginAccounts::enabled(), which is a denylist of
 * production alone and therefore on for staging. Signed URLs for a stand-in
 * account must not be renderable there. The routes are registered at boot, so
 * this asserts on the guard in the route file rather than re-registering it.
 */
test('the preview routes are registered only for named safe environments', function (string $environment) {
    // Routes are registered at boot, so the guard is exercised by booting a
    // fresh kernel under the environment rather than by re-including the file.
    $output = Process::env(['APP_ENV' => $environment])
        ->run('php artisan route:list --except-vendor --path=dev/mails')
        ->output();

    expect($output)->not->toContain('dev.mails');
})->with(['production', 'staging']);
