<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base class for the application's queued jobs.
 *
 * `php artisan make:job` produces a standalone class implementing ShouldQueue.
 * Extending this instead gives every job the defaults that a job which runs
 * against a database-backed queue needs, in one place:
 *
 *   - a bounded number of attempts, so a permanently broken job lands in
 *     failed_jobs rather than being retried forever;
 *   - exponential backoff, so a job failing on a rate limit or a database
 *     that has gone away does not hammer the dependency it is waiting for;
 *   - a per-attempt execution ceiling, so one wedged attempt cannot occupy a
 *     worker indefinitely;
 *   - a failure hook that logs the job's class, queue and error, since a
 *     failed_jobs row on its own is easy to never look at.
 *
 * Jobs remain free to override any of these as properties or methods; the
 * values here are only the defaults.
 *
 * Dispatching a job requires a worker to be running. `composer run dev` starts
 * one for you (`queue:listen --tries=1`, which reboots per job so new code is
 * picked up without a restart); run `php artisan queue:work` by hand if you are
 * not using it. In the container it is the `worker` role -- see
 * docker/README.md.
 */
abstract class Job implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions before failing outright.
     *
     * Distinct from $tries: a job released back onto the queue deliberately
     * (a rate limiter, a lock it could not take) should not burn its budget
     * of genuine errors.
     */
    public int $maxExceptions = 2;

    /**
     * The seconds the job may run before the worker kills it.
     *
     * Must stay BELOW the queue connection's retry_after (90s by default, see
     * config/queue.php) -- a job still running when retry_after elapses is
     * handed to a second worker while the first is mid-flight, and runs twice.
     */
    public int $timeout = 60;

    /**
     * Delete the job when the model it was dispatched with no longer exists.
     *
     * Without this, a job queued for a record deleted before the worker
     * reached it fails with a ModelNotFoundException, which is noise rather
     * than a fault worth investigating.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Seconds to wait before each successive retry.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    /**
     * Handle a job that has exhausted its attempts.
     *
     * The queue writes the row to failed_jobs either way; this puts the same
     * failure in the application log, where it is seen alongside everything
     * else rather than only by someone who thinks to run `queue:failed`.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Queued job failed.', [
            'job' => static::class,
            'queue' => $this->queue ?? 'default',
            'exception' => $exception?->getMessage(),
        ]);
    }
}
