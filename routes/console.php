<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Laravel 11+ defines the schedule here rather than in a Kernel. One process
| runs it -- the `scheduler` role in docker-compose (CONTAINER_ROLE=scheduler),
| which runs `schedule:work` under supervisor.
|
| Every task below is housekeeping that any project built on this boilerplate
| needs regardless of its domain. Application-specific tasks go underneath.
|
| Two guards are applied throughout, and new tasks should keep both:
|
|   withoutOverlapping() -- a slow run must not stack a second copy on top of
|   the first. The lock lives in the cache store (CACHE_STORE=database), and
|   is given an explicit expiry so a task killed with SIGKILL -- which skips
|   the release -- cannot hold its lock for the default 24 hours.
|
|   onOneServer() -- only one scheduler instance may claim each task. Today
|   the stack runs a single scheduler container, so this is a no-op; it is
|   here so scaling to two does not silently double every run. It requires a
|   lock-capable cache driver (database qualifies; `file` and `array` do not).
|
*/

/**
 * Drop failed jobs that are too old to be worth retrying.
 *
 * Without this the failed_jobs table grows without bound -- it is the one
 * queue table nothing ever deletes from, since a failed job is kept
 * deliberately for inspection.
 */
Schedule::command('queue:prune-failed', ['--hours' => 336])
    ->daily()
    ->withoutOverlapping(60)
    ->onOneServer()
    ->description('Prune failed jobs older than 14 days');

/**
 * Drop finished job batches.
 *
 * Only relevant once the application dispatches batches, but harmless before
 * then: with no rows the command is a single fast DELETE.
 */
Schedule::command('queue:prune-batches', ['--hours' => 48, '--unfinished' => 336])
    ->daily()
    ->withoutOverlapping(60)
    ->onOneServer()
    ->description('Prune finished job batches older than 48 hours');

/**
 * Sweep expired rows out of the database-backed session and cache tables.
 *
 * SESSION_DRIVER and CACHE_STORE are both `database` in every deployed
 * environment, and neither store prunes itself: an expired row is ignored on
 * read but never deleted, so both tables grow for the life of the app. Note
 * Laravel's own cache:prune-stale-tags is Redis-only and does nothing here.
 */
Schedule::command('app:prune-expired-storage')
    ->hourly()
    ->withoutOverlapping(30)
    ->onOneServer()
    ->description('Prune expired session and cache rows');

/**
 * Restart queue workers nightly.
 *
 * Long-lived PHP processes accumulate memory; --max-time in the supervisor
 * command already recycles them, and this makes a deploy-independent restart
 * explicit. queue:restart is graceful -- workers finish the job in hand.
 */
Schedule::command('queue:restart')
    ->dailyAt('03:00')
    ->onOneServer()
    ->description('Gracefully recycle queue workers');
