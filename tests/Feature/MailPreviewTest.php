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
 * The index brands from the BusinessName setting too. It is modelled on the
 * error preview index, which brands from config('app.name') because its pages
 * must render with the database down -- that exemption does not reach here,
 * and an app.name heading would disagree with every message listed under it.
 */
test('the index is branded with the business name rather than the app name', function () {
    config(['app.name' => 'Boilerplate']);

    app(Settings::class)->setMany(['business_name' => 'Acme Widgets']);

    $this->get('/dev/mails')
        ->assertSuccessful()
        ->assertSee('Acme Widgets')
        ->assertDontSee('Boilerplate');
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
 * account must not be renderable there.
 *
 * The routes are registered at boot, so the guard is exercised by booting a
 * fresh kernel under each environment rather than by re-including the route
 * file. Every run is asserted to have SUCCEEDED and the local run to have
 * listed the routes: without those, a subprocess that merely crashed would
 * produce output containing no "dev.mails" and pass this vacuously -- the
 * failure mode that matters most for a security guard.
 */
function routeListFor(string $environment): string
{
    $result = Process::env(['APP_ENV' => $environment])
        ->run('php artisan route:list --except-vendor --path=dev/mails');

    // A non-zero exit would otherwise read as "the routes are absent".
    expect($result->successful())->toBeTrue();

    return $result->output();
}

test('the preview routes exist in the safe environments', function (string $environment) {
    expect(routeListFor($environment))->toContain('dev.mails');
})->with(['local', 'testing']);

test('the preview routes are absent outside the safe environments', function (string $environment) {
    expect(routeListFor($environment))->not->toContain('dev.mails');
})->with(['production', 'staging']);
