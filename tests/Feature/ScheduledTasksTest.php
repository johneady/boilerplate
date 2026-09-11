<?php

use App\Jobs\Job;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Every command the schedule registers, as it would be run.
 *
 * @return array<int, string>
 */
function scheduledCommands(): array
{
    return collect(app(Schedule::class)->events())
        ->map(fn (Event $event): string => $event->command ?? '')
        ->all();
}

/**
 * Locate a scheduled event by the artisan command it runs.
 */
function scheduledEventFor(string $command): Event
{
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, $command));

    expect($event)->not->toBeNull("No scheduled task runs [{$command}].");

    return $event;
}

test('the housekeeping tasks every project needs are scheduled', function (string $command) {
    $matching = array_filter(
        scheduledCommands(),
        fn (string $scheduled): bool => str_contains($scheduled, $command),
    );

    expect($matching)->toHaveCount(1, "Expected exactly one scheduled task running [{$command}].");
})->with([
    'queue:prune-failed',
    'queue:prune-batches',
    'app:prune-expired-storage',
    'queue:restart',
]);

/**
 * A slow run must not stack a second copy on top of the first, and a second
 * scheduler instance must not double every run. Both guards are easy to forget
 * when adding a task, and neither fails visibly in development -- the symptom
 * only appears under load or after scaling.
 */
test('recurring maintenance tasks cannot overlap or double-run', function (string $command) {
    $event = scheduledEventFor($command);

    expect($event->withoutOverlapping)->toBeTrue("[{$command}] may overlap itself.")
        ->and($event->onOneServer)->toBeTrue("[{$command}] may run on every scheduler instance.");
})->with([
    'queue:prune-failed',
    'queue:prune-batches',
    'app:prune-expired-storage',
]);

/**
 * withoutOverlapping()'s default expiry is 24 hours. A task killed with SIGKILL
 * never releases its lock, so an hourly task taking the default would stay
 * blocked for a day after a single hard restart.
 */
test('overlap locks expire well inside a day', function () {
    $event = scheduledEventFor('app:prune-expired-storage');

    expect($event->expiresAt)->toBeLessThan(1440);
});

test('queued jobs retry a bounded number of times and then stop', function () {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    expect($job->tries)->toBeGreaterThan(1)
        ->and($job->tries)->toBeLessThanOrEqual(5)
        ->and($job->backoff())->not->toBeEmpty();
});

/**
 * A job still running when the connection's retry_after elapses is handed to a
 * second worker while the first is mid-flight, so it runs twice. Keeping the
 * job timeout below retry_after is what prevents that, and the two values live
 * in different files -- so assert the relationship rather than either number.
 */
test('the job timeout stays below the queue retry_after', function () {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    $retryAfter = (int) config('queue.connections.database.retry_after');

    expect($job->timeout)->toBeLessThan($retryAfter);
});
