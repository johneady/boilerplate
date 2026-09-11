<?php

use Illuminate\Support\Facades\DB;

/**
 * The suite runs with CACHE_STORE=array and SESSION_DRIVER=array (phpunit.xml),
 * but the command only prunes database-backed stores. Point both at the
 * database driver so the behaviour under test is reachable at all -- and so the
 * "not database" skip paths are exercised deliberately rather than by accident.
 */
function useDatabaseStores(): void
{
    config()->set('cache.default', 'database');
    config()->set('cache.stores.database', [
        'driver' => 'database',
        'connection' => null,
        'table' => 'cache',
        'lock_connection' => null,
        'lock_table' => null,
    ]);
    config()->set('session.driver', 'database');
    config()->set('session.connection', null);
    config()->set('session.table', 'sessions');
    config()->set('session.lifetime', 120);
}

test('it deletes expired cache entries and leaves live ones', function () {
    useDatabaseStores();

    DB::table('cache')->insert([
        ['key' => 'stale', 'value' => 'x', 'expiration' => now()->subHour()->getTimestamp()],
        ['key' => 'fresh', 'value' => 'x', 'expiration' => now()->addHour()->getTimestamp()],
    ]);

    $this->artisan('app:prune-expired-storage', ['--cache-only' => true])
        ->assertSuccessful();

    expect(DB::table('cache')->where('key', 'stale')->exists())->toBeFalse()
        ->and(DB::table('cache')->where('key', 'fresh')->exists())->toBeTrue();
});

/**
 * A lock row is normally deleted when the lock is released, so a row left
 * behind is the residue of a process killed mid-lock -- exactly the rows that
 * would otherwise block a withoutOverlapping() task until their expiry.
 */
test('it deletes expired cache locks and leaves held ones', function () {
    useDatabaseStores();

    DB::table('cache_locks')->insert([
        ['key' => 'abandoned', 'owner' => 'dead', 'expiration' => now()->subMinute()->getTimestamp()],
        ['key' => 'held', 'owner' => 'alive', 'expiration' => now()->addMinutes(10)->getTimestamp()],
    ]);

    $this->artisan('app:prune-expired-storage', ['--cache-only' => true])
        ->assertSuccessful();

    expect(DB::table('cache_locks')->where('key', 'abandoned')->exists())->toBeFalse()
        ->and(DB::table('cache_locks')->where('key', 'held')->exists())->toBeTrue();
});

/**
 * Expiry is measured from last_activity against session.lifetime, not from a
 * column of its own, so a session is pruned only once it has been idle longer
 * than the configured lifetime.
 */
test('it deletes sessions idle for longer than the session lifetime', function () {
    useDatabaseStores();

    DB::table('sessions')->insert([
        ['id' => 'idle', 'payload' => '', 'last_activity' => now()->subMinutes(180)->getTimestamp()],
        ['id' => 'recent', 'payload' => '', 'last_activity' => now()->subMinutes(5)->getTimestamp()],
    ]);

    $this->artisan('app:prune-expired-storage', ['--sessions-only' => true])
        ->assertSuccessful();

    expect(DB::table('sessions')->where('id', 'idle')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'recent')->exists())->toBeTrue();
});

test('--cache-only leaves sessions alone', function () {
    useDatabaseStores();

    DB::table('sessions')->insert([
        'id' => 'idle', 'payload' => '', 'last_activity' => now()->subMinutes(180)->getTimestamp(),
    ]);

    $this->artisan('app:prune-expired-storage', ['--cache-only' => true])
        ->assertSuccessful();

    expect(DB::table('sessions')->where('id', 'idle')->exists())->toBeTrue();
});

test('--sessions-only leaves the cache alone', function () {
    useDatabaseStores();

    DB::table('cache')->insert([
        'key' => 'stale', 'value' => 'x', 'expiration' => now()->subHour()->getTimestamp(),
    ]);

    $this->artisan('app:prune-expired-storage', ['--sessions-only' => true])
        ->assertSuccessful();

    expect(DB::table('cache')->where('key', 'stale')->exists())->toBeTrue();
});

/**
 * The command must be a no-op rather than an error when a project built on this
 * boilerplate moves either store to Redis -- the scheduler runs it hourly
 * regardless, and a failing task would page someone every hour for nothing.
 */
test('it skips stores that are not database backed', function () {
    config()->set('cache.default', 'array');
    config()->set('session.driver', 'array');

    $this->artisan('app:prune-expired-storage')
        ->expectsOutputToContain('Session driver is not [database]')
        ->expectsOutputToContain('Cache store is not [database]')
        ->assertSuccessful();
});
