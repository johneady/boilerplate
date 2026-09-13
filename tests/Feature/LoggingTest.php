<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Resolve a fresh stack over the given channels.
 *
 * The log manager caches each channel it builds, so a config change made
 * after one has been resolved is not otherwise picked up.
 *
 * @param  array<int, string>  $channels
 */
function resolveLogStack(array $channels): Logger
{
    Config::set('logging.channels.stack.channels', $channels);

    Log::forgetChannel('stack');

    return Log::channel('stack')->getLogger();
}

test('the daily channel writes through a rotating handler', function () {
    // The reason the deployed stacks set LOG_STACK=daily: nothing rotates a
    // log file inside a container, so the 'single' channel's plain
    // StreamHandler grows for the life of the volume until it fills the disk.
    $handler = resolveLogStack(['daily'])->getHandlers()[0];

    expect($handler)->toBeInstanceOf(RotatingFileHandler::class);
});

test('the single channel does not rotate, which is why it is not the deployed default', function () {
    $handler = resolveLogStack(['single'])->getHandlers()[0];

    expect($handler)->toBeInstanceOf(StreamHandler::class)
        ->and($handler)->not->toBeInstanceOf(RotatingFileHandler::class);
});

test('the retention period is taken from LOG_DAILY_DAYS', function () {
    // Read back off the handler rather than the config array, so a channel
    // that silently ignored the setting would fail here.
    Config::set('logging.channels.daily.max_files', 7);

    $handler = resolveLogStack(['daily'])->getHandlers()[0];

    $maxFiles = (new ReflectionProperty(RotatingFileHandler::class, 'maxFiles'))
        ->getValue($handler);

    expect($maxFiles)->toBe(7);
});

test('the retention period defaults to fourteen days, as an integer', function () {
    // A value read from .env arrives as the string '14', which is not what a
    // RotatingFileHandler's int maxFiles wants, so the config casts it. The
    // strict comparison is the point of this test: it fails if the cast goes.
    expect(config('logging.channels.daily.max_files'))->toBe(14);
});
