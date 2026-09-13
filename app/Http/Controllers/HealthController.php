<?php

namespace App\Http\Controllers;

use App\Listeners\RecordWorkerHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reports whether the application's backing services are actually working.
 *
 * Laravel's /up (registered in bootstrap/app.php) returns 200 as soon as the
 * framework boots. That is the right signal to restart a container on, but it
 * is nearly silent about this stack: the queue, the cache and the sessions all
 * live in the database, and the workers run in their own containers. Every one
 * of those can be broken while /up is green.
 *
 * So this endpoint resolves each dependency and says which one failed. See
 * config/health.php for why the two endpoints are kept separate rather than
 * deepening /up in place.
 *
 * The response body names the failing check but never says why: the exception
 * message can carry a database host, a username or a file path, and this route
 * is unauthenticated. The detail goes to the caller's logs via the exception
 * handler, not into the body.
 */
class HealthController extends Controller
{
    /**
     * Run every check and report the result.
     *
     * Returns 503 rather than 200-with-a-body when anything is degraded, so an
     * uptime monitor that only understands status codes still notices. 503 is
     * also what a load balancer reads as "do not send traffic here", which is
     * the correct handling for an instance whose database is gone.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        $healthy = collect($checks)->every(fn (array $check): bool => $check['status'] !== 'failing');

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    /**
     * Confirm the default database connection answers a query.
     *
     * `SELECT 1` rather than getPdo(): the connection is lazy and pooled, so
     * holding a PDO object proves only that one was constructed at some point.
     * A round trip is what distinguishes a live server from a socket that has
     * since gone away.
     *
     * @return array{status: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->select('select 1');
        } catch (Throwable) {
            return ['status' => 'failing'];
        }

        return ['status' => 'ok'];
    }

    /**
     * Confirm the cache store accepts a write and returns it.
     *
     * Writes and reads back rather than reading a known key, because the
     * database cache store fails asymmetrically: a read against a missing
     * table returns null, which is indistinguishable from a cache miss, while
     * the write is what actually touches the table. The value is random so
     * concurrent callers cannot read each other's and mask a store that is
     * silently discarding writes.
     *
     * @return array{status: string}
     */
    private function checkCache(): array
    {
        $key = 'health:cache-probe';
        $value = Str::random(16);

        try {
            Cache::put($key, $value, now()->addMinute());

            if (Cache::get($key) !== $value) {
                return ['status' => 'failing'];
            }

            Cache::forget($key);
        } catch (Throwable) {
            return ['status' => 'failing'];
        }

        return ['status' => 'ok'];
    }

    /**
     * Report how long ago a worker last finished a job.
     *
     * This is the check /up cannot approximate at all. The container
     * healthchecks ask supervisor whether the worker process is RUNNING, which
     * a worker wedged on a dead database connection still is; the heartbeat
     * written by App\Listeners\RecordWorkerHeartbeat only advances when a job
     * is actually carried to completion.
     *
     * Three states, deliberately distinguished:
     *
     *   skipped -- no worker is expected (QUEUE_CONNECTION=sync runs jobs
     *   inline) or the check is switched off. Not a fault.
     *
     *   unknown -- no heartbeat has ever been recorded. Reported rather than
     *   failed, because it is the honest state of a freshly deployed instance
     *   whose worker has not yet had a job to run. Failing here would make
     *   every deploy go red for its first hour.
     *
     *   failing -- a heartbeat exists but is older than the configured
     *   ceiling. That is the wedged-worker signal: something WAS running and
     *   has stopped, which an idle queue cannot produce while the hourly
     *   scheduled task keeps feeding it.
     *
     * @return array{status: string, last_processed_age?: int}
     */
    private function checkQueue(): array
    {
        if (! config('health.check_queue') || config('queue.default') === 'sync') {
            return ['status' => 'skipped'];
        }

        try {
            $lastProcessedAt = Cache::get(RecordWorkerHeartbeat::CACHE_KEY);
        } catch (Throwable) {
            // The cache check above already reports an unreachable store;
            // repeating it here would turn one fault into two alerts.
            return ['status' => 'unknown'];
        }

        if (! is_numeric($lastProcessedAt)) {
            return ['status' => 'unknown'];
        }

        $age = now()->getTimestamp() - (int) $lastProcessedAt;
        $maxAge = (int) config('health.queue_heartbeat_max_age');

        return [
            'status' => $age > $maxAge ? 'failing' : 'ok',
            'last_processed_age' => $age,
        ];
    }
}
