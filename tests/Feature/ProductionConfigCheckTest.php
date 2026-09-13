<?php

/**
 * Put the application into a deployed environment with an otherwise clean
 * configuration, so each test can introduce exactly one fault.
 *
 * 'staging' rather than 'production' throughout, and that is the point: the
 * Dokploy compose file lets APP_ENV be overridden, so a staging instance owns
 * a real database and must be audited identically. A command written as
 * `isProduction()` would pass every test below while auditing nothing.
 */
function configureDeployedEnvironment(): void
{
    app()->detectEnvironment(fn () => 'staging');

    config()->set('app.debug', false);
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('app.url', 'https://example.com');
    config()->set('trustedproxy.proxies', ['10.0.0.1']);
    config()->set('logging.default', 'stack');
    config()->set('logging.channels.stack.channels', ['daily']);
    config()->set('logging.level', 'warning');
    config()->set('session.secure', true);
    config()->set('session.encrypt', true);
    config()->set('session.driver', 'database');
    config()->set('mail.default', 'smtp');
    config()->set('mail.from.address', 'hello@example.com');
    config()->set('queue.default', 'database');
    config()->set('cache.default', 'database');
    config()->set('filesystems.default', 's3');
    config()->set('dev-login.blocked_environments', ['production', 'staging']);
}

afterEach(function () {
    app()->detectEnvironment(fn () => 'testing');
});

test('the checks do not run in local or testing', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);

    // APP_DEBUG=true is a failure on a deployed instance and correct here, so
    // a command that ignored the environment would exit non-zero.
    config()->set('app.debug', true);

    $this->artisan('app:check-production')
        ->expectsOutputToContain('deployed environments only')
        ->assertSuccessful();
})->with(['local', 'testing']);

test('a correctly configured deployment passes', function () {
    configureDeployedEnvironment();

    $this->artisan('app:check-production')
        ->expectsOutputToContain('Configuration looks correct')
        ->assertSuccessful();
});

test('debug mode fails the check', function () {
    configureDeployedEnvironment();
    config()->set('app.debug', true);

    $this->artisan('app:check-production')
        ->expectsOutputToContain('APP_DEBUG')
        ->assertFailed();
});

test('a missing application key fails the check', function () {
    configureDeployedEnvironment();
    config()->set('app.key', '');

    $this->artisan('app:check-production')
        ->expectsOutputToContain('APP_KEY')
        ->assertFailed();
});

test('a mailer that does not deliver fails the check', function (string $mailer) {
    // The silent failure this command exists for: .env.example ships
    // MAIL_MAILER=log, and carried onto a server it writes every password
    // reset to a file while the user waits for mail that never comes.
    configureDeployedEnvironment();
    config()->set('mail.default', $mailer);

    $this->artisan('app:check-production')
        ->expectsOutputToContain('MAIL_MAILER')
        ->assertFailed();
})->with(['log', 'array']);

test('an empty from address fails the check', function () {
    configureDeployedEnvironment();
    config()->set('mail.from.address', '');

    $this->artisan('app:check-production')
        ->expectsOutputToContain('MAIL_FROM_ADDRESS')
        ->assertFailed();
});

test('an array cache store fails the check', function () {
    configureDeployedEnvironment();
    config()->set('cache.default', 'array');

    $this->artisan('app:check-production')
        ->expectsOutputToContain('CACHE_STORE')
        ->assertFailed();
});

test('warnings alone still exit successfully', function () {
    // A warning names a supported choice with a cost, not a fault. Exiting
    // non-zero on one would make the command useless as a deploy gate, since
    // an installation that has accepted the cost could never pass.
    configureDeployedEnvironment();
    config()->set('session.encrypt', false);

    $this->artisan('app:check-production')
        ->expectsOutputToContain('SESSION_ENCRYPT')
        ->assertSuccessful();
});

test('warnings can be made fatal', function () {
    configureDeployedEnvironment();
    config()->set('session.encrypt', false);

    $this->artisan('app:check-production', ['--warnings-as-errors' => true])
        ->assertFailed();
});

test('passwordless dev login on a deployed instance is reported', function () {
    // The gate is a denylist of production alone, so a staging deploy has
    // one-click logins to accounts whose credentials are public. That is a
    // deliberate project decision; the command's job is to surface it.
    configureDeployedEnvironment();
    config()->set('dev-login.blocked_environments', ['production']);

    $this->artisan('app:check-production')
        ->expectsOutputToContain('dev login')
        ->assertSuccessful();
});

test('a wildcard proxy is reported', function () {
    configureDeployedEnvironment();
    config()->set('trustedproxy.proxies', '*');

    $this->artisan('app:check-production')
        ->expectsOutputToContain('TRUST_PROXIES')
        ->assertSuccessful();
});

test('an unrotated log channel is reported', function () {
    configureDeployedEnvironment();
    config()->set('logging.channels.stack.channels', ['single']);

    $this->artisan('app:check-production')
        ->expectsOutputToContain('single')
        ->assertSuccessful();
});
