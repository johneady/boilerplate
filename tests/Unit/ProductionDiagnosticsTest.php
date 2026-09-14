<?php

use App\Settings\DiagnosticResult;
use App\Settings\DiagnosticSeverity;
use App\Settings\ProductionDiagnostics;
use Tests\TestCase;

/*
 * A unit test rather than a feature one, despite needing the framework booted
 * for config(). tests/Pest.php binds RefreshDatabase across Feature, and that
 * resolves the default connection for every test in it -- which breaks the
 * moment a test repoints database.default away from sqlite, as the "not
 * sqlite in production" checks necessarily do. Nothing here touches the
 * database, so binding TestCase alone is both sufficient and faster.
 */
uses(TestCase::class);

/**
 * Put the configuration into a shape that passes every check, so each test can
 * break exactly one thing and attribute the resulting finding to it. Written
 * as production, because most checks are deliberately inert anywhere else.
 *
 * database.default is set to a name that is merely "not sqlite" rather than a
 * real driver. The check compares only the name, and nothing here opens a
 * connection, so no server has to exist for these tests to run.
 */
function healthyProductionConfig(): void
{
    config()->set('app.env', 'production');
    config()->set('app.debug', false);
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('app.url', 'https://example.com');
    config()->set('database.default', 'not-sqlite');
    config()->set('session.encrypt', true);
    config()->set('session.secure', true);
    config()->set('trustedproxy.proxies', ['10.0.0.1']);
    config()->set('mail.default', 'smtp');
    config()->set('images.disk', 's3');
    config()->set('filesystems.disks.s3', ['driver' => 's3']);
    config()->set('logging.default', 'stack');
    config()->set('logging.channels.stack', ['driver' => 'stack', 'channels' => ['daily']]);
    config()->set('logging.channels.daily', ['driver' => 'daily', 'level' => 'warning']);

    config()->set('fortify.passkeys.has_dedicated_user_handle_secret', true);
}

/**
 * Find one check's result by name.
 */
function diagnosticFor(string $name): DiagnosticResult
{
    $result = collect((new ProductionDiagnostics)->run())
        ->firstWhere('name', $name);

    expect($result)->not->toBeNull("No diagnostic named [{$name}].");

    return $result;
}

test('a healthy production configuration reports no failures', function () {
    healthyProductionConfig();

    expect((new ProductionDiagnostics)->failures())->toBeEmpty();
});

test('debug mode is an error', function () {
    healthyProductionConfig();
    config()->set('app.debug', true);

    $result = diagnosticFor('Debug mode');

    expect($result->passed)->toBeFalse()
        ->and($result->severity)->toBe(DiagnosticSeverity::Error);
});

/**
 * Debug mode is the one check that must fire outside production too: a staging
 * box serving stack traces leaks just as much as a production one.
 */
test('debug mode is reported even outside production', function () {
    healthyProductionConfig();
    config()->set('app.env', 'local');
    config()->set('app.debug', true);

    expect(diagnosticFor('Debug mode')->passed)->toBeFalse();
});

test('a missing application key is an error', function () {
    healthyProductionConfig();
    config()->set('app.key', '');

    $result = diagnosticFor('Application key');

    expect($result->passed)->toBeFalse()
        ->and($result->severity)->toBe(DiagnosticSeverity::Error);
});

test('sqlite in production is an error', function () {
    healthyProductionConfig();
    config()->set('database.default', 'sqlite');

    expect(diagnosticFor('Database driver')->passed)->toBeFalse();
});

/**
 * The suite itself runs on sqlite, so a check that fired regardless of
 * environment would make every local run noisy for no reason.
 */
test('sqlite outside production is not reported', function () {
    healthyProductionConfig();
    config()->set('app.env', 'local');
    config()->set('database.default', 'sqlite');

    expect(diagnosticFor('Database driver')->passed)->toBeTrue();
});

/**
 * The container-local disks are the default in both .env.example and the
 * Dokploy stack, and only storage/logs has a volume mounted over it -- so this
 * catches uploads that silently vanish on the next deploy.
 */
test('a container-local image disk in production is a warning', function () {
    healthyProductionConfig();
    config()->set('images.disk', 'public');
    config()->set('filesystems.disks.public', ['driver' => 'local']);

    $result = diagnosticFor('Image storage');

    expect($result->passed)->toBeFalse()
        ->and($result->severity)->toBe(DiagnosticSeverity::Warning);
});

test('a remote image disk in production passes', function () {
    healthyProductionConfig();

    expect(diagnosticFor('Image storage')->passed)->toBeTrue();
});

/**
 * Judged on the driver rather than the disk name, so a project that points
 * IMAGE_DISK at a disk of its own is assessed on where the files really go.
 */
test('a renamed disk is judged by its driver', function () {
    healthyProductionConfig();
    config()->set('images.disk', 'uploads');
    config()->set('filesystems.disks.uploads', ['driver' => 'local']);

    expect(diagnosticFor('Image storage')->passed)->toBeFalse();
});

test('a container-local image disk outside production is not reported', function () {
    healthyProductionConfig();
    config()->set('app.env', 'local');
    config()->set('images.disk', 'public');
    config()->set('filesystems.disks.public', ['driver' => 'local']);

    expect(diagnosticFor('Image storage')->passed)->toBeTrue();
});

test('unencrypted sessions in production are an error', function () {
    healthyProductionConfig();
    config()->set('session.encrypt', false);

    expect(diagnosticFor('Session encryption')->passed)->toBeFalse();
});

/**
 * The cookie flag is keyed off the URL scheme rather than the environment: an
 * https staging site has exactly the same exposure.
 */
test('an insecure session cookie on an https url is an error', function () {
    healthyProductionConfig();
    config()->set('app.env', 'local');
    config()->set('app.url', 'https://staging.example.com');
    config()->set('session.secure', false);

    $result = diagnosticFor('Secure session cookie');

    expect($result->passed)->toBeFalse()
        ->and($result->severity)->toBe(DiagnosticSeverity::Error);
});

/**
 * Passing for want of https is not the same as passing because the cookie is
 * locked down, and the report must not claim the latter -- the message is the
 * only thing distinguishing them.
 */
test('an insecure session cookie on an http url reports as not applicable', function () {
    healthyProductionConfig();
    config()->set('app.env', 'local');
    config()->set('app.url', 'http://localhost:8000');
    config()->set('session.secure', false);

    $result = diagnosticFor('Secure session cookie');

    expect($result->passed)->toBeTrue()
        ->and($result->detail)->toContain('Not applicable');
});

test('a localhost application url in production is an error', function () {
    healthyProductionConfig();
    config()->set('app.url', 'https://localhost');

    expect(diagnosticFor('Application URL')->passed)->toBeFalse();
});

test('a plain http application url in production is an error', function () {
    healthyProductionConfig();
    config()->set('app.url', 'http://example.com');

    expect(diagnosticFor('Application URL')->passed)->toBeFalse();
});

/**
 * Wildcard proxies are legitimate on a platform whose proxy is the only route
 * in, so this must stay a warning -- an error would fail the Dokploy stack
 * this boilerplate actually ships with.
 */
test('trusting every proxy is a warning rather than an error', function () {
    healthyProductionConfig();
    config()->set('trustedproxy.proxies', '*');

    $result = diagnosticFor('Trusted proxies');

    expect($result->passed)->toBeFalse()
        ->and($result->severity)->toBe(DiagnosticSeverity::Warning);
});

test('the log mailer in production is a warning', function () {
    healthyProductionConfig();
    config()->set('mail.default', 'log');

    $result = diagnosticFor('Mail delivery');

    expect($result->passed)->toBeFalse()
        ->and($result->severity)->toBe(DiagnosticSeverity::Warning);
});

/**
 * The resolved secret falls back to app.key and so is never empty; config
 * records which it was, because the fallback is what breaks every registered
 * passkey on a key rotation.
 */
test('an unset passkey handle secret is a warning', function () {
    healthyProductionConfig();
    config()->set('fortify.passkeys.has_dedicated_user_handle_secret', false);

    $result = diagnosticFor('Passkey handle secret');

    expect($result->passed)->toBeFalse()
        ->and($result->severity)->toBe(DiagnosticSeverity::Warning);
});

/**
 * There is no top-level logging.level key; the level lives on each channel of
 * the active stack, which is what makes this worth pinning.
 */
test('debug logging in production is a warning', function () {
    healthyProductionConfig();
    config()->set('logging.channels.daily', ['driver' => 'daily', 'level' => 'debug']);

    $result = diagnosticFor('Log level');

    expect($result->passed)->toBeFalse()
        ->and($result->severity)->toBe(DiagnosticSeverity::Warning);
});

test('the log level of a non-stack default channel is read directly', function () {
    healthyProductionConfig();
    config()->set('logging.default', 'single');
    config()->set('logging.channels.single', ['driver' => 'single', 'level' => 'debug']);

    expect(diagnosticFor('Log level')->passed)->toBeFalse();
});

test('a channel with no level counts as debug, not as absent', function () {
    healthyProductionConfig();
    // The framework resolves a missing level to debug itself, so a level-less
    // channel IS logging at debug. Dropping it from the check made this
    // diagnostic pass for exactly the unconfigured case it exists to catch.
    config()->set('logging.channels.daily', ['driver' => 'daily']);

    expect(diagnosticFor('Log level')->passed)->toBeFalse();
});

test('failures are ordered with errors before warnings', function () {
    healthyProductionConfig();
    config()->set('mail.default', 'log');
    config()->set('app.debug', true);

    $severities = array_map(
        fn (DiagnosticResult $result): DiagnosticSeverity => $result->severity,
        (new ProductionDiagnostics)->failures(),
    );

    expect($severities)->toBe([DiagnosticSeverity::Error, DiagnosticSeverity::Warning]);
});

test('passing checks describe the state that passed', function () {
    healthyProductionConfig();

    $result = diagnosticFor('Debug mode');

    expect($result->passed)->toBeTrue()
        ->and($result->severity)->toBe(DiagnosticSeverity::Passed)
        ->and($result->detail)->toContain('Debug mode is off');
});
