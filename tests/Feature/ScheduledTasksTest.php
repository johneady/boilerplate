<?php

use App\Jobs\Job;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;

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

/**
 * Rebuild the schedule from routes/console.php in the given queue mode.
 *
 * The file reads config('queue.mode') once, at boot, so a test that only
 * changes the config would still see the schedule the suite booted with.
 *
 * @return array<int, Event>
 */
function scheduleInQueueMode(string $mode, string $connection = 'database'): array
{
    config()->set('queue.mode', $mode);
    config()->set('queue.default', $connection);

    app()->instance(Schedule::class, new Schedule(config('app.schedule_timezone')));
    ScheduleFacade::clearResolvedInstance(Schedule::class);

    require base_path('routes/console.php');

    return app(Schedule::class)->events();
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
    'app:prune-audit-log',
    'app:adopt-page-body-images',
    'app:prune-orphaned-media',
    'queue:restart',
    'payments:reconcile-stale',
    'payments:expire-checkouts',
    'payments:check-authorizations',
    'payments:prune-webhook-events',
    'payments:end-subscriptions',
    'payments:notify-trials-ending',
    'app:prune-exports',
    'app:send-business-summary',
    'update:prune',
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
    'app:prune-audit-log',
    'app:adopt-page-body-images',
    'app:prune-orphaned-media',
    'queue:restart',
    'payments:reconcile-stale',
    'payments:expire-checkouts',
    'payments:check-authorizations',
    'payments:prune-webhook-events',
    'payments:end-subscriptions',
    'payments:notify-trials-ending',
    'app:prune-exports',
    'app:send-business-summary',
    'update:prune',
]);

/**
 * withoutOverlapping()'s default expiry is 24 hours. A task killed with SIGKILL
 * never releases its lock, so an hourly task taking the default would stay
 * blocked for a day after a single hard restart.
 */
test('overlap locks expire well inside a day', function (string $command) {
    expect(scheduledEventFor($command)->expiresAt)->toBeLessThan(1440);
})->with([
    'app:prune-expired-storage',
    'payments:reconcile-stale',
    'payments:expire-checkouts',
    'payments:check-authorizations',
    'payments:prune-webhook-events',
    'payments:end-subscriptions',
    'payments:notify-trials-ending',
    'app:prune-exports',
    'app:send-business-summary',
    'update:prune',
]);

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

test('cron queue mode drains the queue every minute, after every other task', function () {
    $events = scheduleInQueueMode('cron');
    $worker = end($events);

    expect($worker->command)->toContain('queue:work')
        ->toContain('--stop-when-empty ')
        ->not->toContain('--stop-when-empty=')
        ->toContain('--max-time=50')
        ->toContain("--queue='high,default'")
        ->toContain('--tries=3')
        ->and($worker->expression)->toBe('* * * * *')
        ->and($worker->withoutOverlapping)->toBeTrue()
        ->and($worker->expiresAt)->toBe(10)
        ->and($worker->onOneServer)->toBeTrue();
});

test('worker queue mode leaves the queue to its long-running worker', function () {
    $commands = collect(scheduleInQueueMode('worker'))
        ->map(fn (Event $event): string => (string) $event->command);

    expect($commands->filter(fn (string $command): bool => str_contains($command, 'queue:work')))->toBeEmpty();
});

test('cron queue mode schedules nothing on the sync connection, where jobs already ran inline', function () {
    $commands = collect(scheduleInQueueMode('cron', 'sync'))
        ->map(fn (Event $event): string => (string) $event->command);

    expect($commands->filter(fn (string $command): bool => str_contains($command, 'queue:work')))->toBeEmpty();
});

/**
 * The web installer writes a .env with no QUEUE_MODE, onto a host with no
 * worker, so anything but an explicit "worker" must mean cron.
 */
test('the queue mode is cron unless worker is asked for', function (?string $value, string $mode) {
    $resolved = withEnvironment(['QUEUE_MODE' => $value], fn (): mixed => (require config_path('queue.php'))['mode']);

    expect($resolved)->toBe($mode);
})->with([
    'unset' => [null, 'cron'],
    'cron' => ['cron', 'cron'],
    'worker' => ['worker', 'worker'],
    'worker, any case' => [' Worker ', 'worker'],
    'unrecognised' => ['supervisor', 'cron'],
]);
