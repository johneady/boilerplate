<?php

use App\Providers\AppServiceProvider;
use Illuminate\Database\Console\WipeCommand;

/**
 * Re-run the provider's defaults as the given environment, then report whether
 * a destructive command actually refuses to run.
 */
function wipeIsProhibitedIn(string $environment): bool
{
    app()->detectEnvironment(fn () => $environment);

    (new AppServiceProvider(app()))->boot();

    return (new ReflectionClass(WipeCommand::class))
        ->getStaticPropertyValue('prohibitedFromRunning');
}

afterEach(function () {
    // The flag is static, so leaving it set would leak into later tests.
    app()->detectEnvironment(fn () => 'testing');
    (new AppServiceProvider(app()))->boot();
});

test('destructive commands are prohibited in production', function () {
    expect(wipeIsProhibitedIn('production'))->toBeTrue();
});

test('destructive commands stay prohibited on a deployed staging instance', function () {
    // APP_ENV is overridable in docker-compose.dokploy.yml, so a staging
    // deploy must not silently lose the guard that keeps migrate:fresh and
    // db:wipe off a database holding real data.
    expect(wipeIsProhibitedIn('staging'))->toBeTrue();
});

test('destructive commands are allowed in local and testing', function () {
    expect(wipeIsProhibitedIn('local'))->toBeFalse()
        ->and(wipeIsProhibitedIn('testing'))->toBeFalse();
});
