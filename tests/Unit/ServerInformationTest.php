<?php

use App\Settings\ServerInformation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 * A unit test rather than a feature one, matching ProductionDiagnosticsTest:
 * nothing here needs a migrated database -- the report reads the server it
 * runs on, and the default connection resolves without tables when asked
 * only for its driver and version.
 */
uses(TestCase::class);

test('php.ini size shorthand parses to bytes', function (string $value, ?int $bytes) {
    expect(ServerInformation::toBytes($value))->toBe($bytes);
})->with([
    'plain bytes' => ['1024', 1024],
    'kilobytes' => ['8K', 8192],
    'megabytes' => ['128M', 134217728],
    'gigabytes' => ['2G', 2147483648],
    'case insensitive' => ['2g', 2147483648],
    'unlimited' => ['-1', null],
    'not a size' => ['steve', null],
    'empty' => ['', null],
]);

test('bytes format the size a person reads', function (?int $bytes, ?string $formatted) {
    expect(ServerInformation::formatBytes($bytes))->toBe($formatted);
})->with([
    'nothing offered' => [null, null],
    'zero' => [0, '0 B'],
    'bytes' => [512, '512 B'],
    'kilobytes whole' => [2 * 1024, '2 KB'],
    'kilobytes fractional' => [1536, '1.5 KB'],
    'megabytes' => [5 * 1048576, '5 MB'],
    'gigabytes fractional' => [3 * 1073741824, '3 GB'],
]);

test('byte limits describe themselves', function (string|false $value, ?string $described) {
    expect(ServerInformation::describeByteLimit($value))->toBe($described);
})->with([
    'a shorthand limit' => ['128M', '128 MB'],
    'unlimited' => ['-1', 'Unlimited'],
    'unreadable' => [false, null],
]);

test('time limits describe themselves', function (string|false $value, ?string $described) {
    expect(ServerInformation::describeTimeLimit($value))->toBe($described);
})->with([
    'a limit' => ['30', '30 seconds'],
    'unlimited' => ['0', 'Unlimited'],
    'unreadable' => [false, null],
]);

test('the platform section reports the machine it runs on', function () {
    $rows = (new ServerInformation)->platform();

    expect($rows)->toHaveKey('Operating system', PHP_OS_FAMILY)
        ->and($rows)->toHaveKey('PHP interface', PHP_SAPI)
        ->and($rows)->toHaveKey('Server clock');
});

test('the runtime section reports the build it runs under', function () {
    $rows = (new ServerInformation)->runtime();

    expect($rows)->toHaveKey('PHP version', PHP_VERSION)
        ->and($rows['Laravel'])->toStartWith(app()->version())
        ->and($rows['Loaded extensions'])->toBe((string) count(get_loaded_extensions()));
});

/**
 * The exact status depends on how PHP was launched, so the pin is that the
 * section always says which of the three states it is in -- an absent
 * OPcache is itself a fact worth reporting.
 */
test('the opcache section always states its status', function () {
    $rows = (new ServerInformation)->opcache();

    expect($rows)->toHaveKey('Status')
        ->and($rows['Status'])->toBeIn(['Enabled', 'Not enabled', 'Not installed']);
});

test('the database section reports the connection behind these tests', function () {
    $rows = (new ServerInformation)->database();

    // Not pinned to sqlite: CI runs this same suite against MySQL and
    // MariaDB, so what is asserted is that the section reports whichever
    // driver is actually connected, not which one it happens to be.
    expect($rows)->toHaveKey('Driver', DB::connection()->getDriverName())
        ->and($rows['Server version'])->toBeString()
        ->not->toBeEmpty();
});

test('an in-memory sqlite database reports no size', function () {
    // The suite's in-memory database occupies no file, so there is no size
    // to report -- the row is omitted rather than guessed.
    $rows = (new ServerInformation)->database();

    expect($rows)->not->toHaveKey('Size');
})->skip(
    fn () => DB::connection()->getDriverName() !== 'sqlite',
    'Only an in-memory sqlite database has no size to measure.',
);

test('the disk section measures the filesystem the project lives on', function () {
    $disk = (new ServerInformation)->disk();

    expect($disk['rows'])->toHaveKeys(['Total', 'Available', 'Used'])
        ->and($disk['usedPercent'])->toBeFloat()
        ->toBeGreaterThanOrEqual(0)
        ->toBeLessThanOrEqual(100);
});

test('the extensions are listed alphabetically', function () {
    $extensions = (new ServerInformation)->extensions();

    $expected = get_loaded_extensions();
    sort($expected);

    expect($extensions)->toContain('Core')
        ->and($extensions)->toBe($expected);
});
