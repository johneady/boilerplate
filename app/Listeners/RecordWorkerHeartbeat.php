<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Stamp the time a queue worker last finished a job.
 *
 * The container healthchecks ask supervisor whether the worker PROCESS is
 * running (see .ai/rules/queues-and-scheduling.md), which is the right signal
 * for "restart this container" but does not answer "is the queue moving".
 * A worker wedged on a lock, or one holding a database connection that has
 * gone away, stays RUNNING while jobs pile up untouched.
 *
 * This marker is that second signal, and App\Http\Controllers\HealthController
 * reads it. It is written on JobProcessed rather than JobProcessing so the
 * heartbeat means a job was carried to completion, not merely picked up.
 *
 * Registered by Laravel's listener auto-discovery, from the event type hinted
 * below -- the same route as SendQueueFailureAlert, which is why neither
 * appears in a provider.
 */
class RecordWorkerHeartbeat
{
    /**
     * The cache key holding the last completion timestamp.
     */
    public const string CACHE_KEY = 'queue:worker:last-processed-at';

    /**
     * Handle the event.
     *
     * The marker outlives the staleness threshold the health check applies by
     * a wide margin, so a queue that has simply been idle overnight still
     * reports a real (if old) timestamp rather than a missing one. The health
     * check distinguishes "never seen" from "seen, long ago" and the two mean
     * different things -- a fresh deploy versus a stalled worker.
     */
    public function handle(JobProcessed $event): void
    {
        // Writing the heartbeat is strictly additive, exactly as the failure
        // alert is: this runs inside the worker loop, and an exception here
        // would propagate into the worker's own post-job handling. A cache
        // store that is unreachable is already the health check's problem to
        // report -- it must not also become the reason a successfully
        // processed job looks like a failure.
        try {
            Cache::put(self::CACHE_KEY, now()->getTimestamp(), now()->addDay());
        } catch (Throwable) {
            // Deliberately silent. The database-check half of /health reports
            // a cache store that cannot be written, and logging here would
            // write a line per job on a broken store.
        }
    }
}
