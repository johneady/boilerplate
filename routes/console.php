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
 * Trim the audit trail to its retention period.
 *
 * The audit table takes a row per model change, sign-in and rejected sign-in,
 * so it is the fastest-growing table here and a failed-login burst adds
 * thousands in minutes. Retention is time-based only -- see
 * App\Console\Commands\PruneAuditLog for why it must stay that way.
 */
Schedule::command('app:prune-audit-log')
    ->daily()
    ->withoutOverlapping(60)
    ->onOneServer()
    ->description('Prune audit log entries past their retention period');

/*
 * Hourly rather than daily: an abandoned upload holds real bytes on the disk,
 * and the grace period in config('media.orphan_retention_hours') already
 * decides how long one is kept -- running more often only makes collection
 * prompt once that window has passed, it does not shorten it.
 */
Schedule::command('app:prune-orphaned-media')
    ->hourly()
    ->withoutOverlapping(60)
    ->onOneServer()
    ->description('Delete media rows with no owning record, and their files');

/*
 * Daily rather than hourly, and deliberately AFTER the orphan prune in this
 * file: this one walks the disk and re-encodes, which is real work, while the
 * files it finds are discovered rather than urgent. The grace period in
 * config('media.orphan_retention_hours') already decides how long an
 * unreferenced upload survives, so running more often would not shorten it.
 */
Schedule::command('app:adopt-page-body-images')
    ->daily()
    ->withoutOverlapping(60)
    ->onOneServer()
    ->description('Adopt referenced page-body uploads into the media library, and collect the rest');

/*
 * Daily: an export is announced by a notification the same minute it
 * finishes, so a week's retention is measured in days, not hours.
 */
Schedule::command('app:prune-exports')
    ->daily()
    ->withoutOverlapping(60)
    ->onOneServer()
    ->description('Delete exports older than a week, and their files');

/**
 * Restart queue workers nightly.
 *
 * Long-lived PHP processes accumulate memory; --max-time in the supervisor
 * command already recycles them, and this makes a deploy-independent restart
 * explicit. queue:restart is graceful -- workers finish the job in hand.
 */
Schedule::command('queue:restart')
    ->dailyAt('03:00')
    ->withoutOverlapping(60)
    ->onOneServer()
    ->description('Gracefully recycle queue workers');

/*
|--------------------------------------------------------------------------
| Payments
|--------------------------------------------------------------------------
|
| Each is a no-op while no payment is in the state it looks for, so they are
| scheduled whether or not payments are switched on: a site that turns
| payments off still has holds to warn about and refunds to finish.
|
*/

/*
 * Every 15 minutes: the recovery path for a gateway call whose result was
 * never recorded. Its window (config('payments.stale_after_minutes')) is the
 * longest a refund can sit unconfirmed after a crash.
 */
Schedule::command('payments:reconcile-stale')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->onOneServer()
    ->description('Re-read payments and refunds whose last gateway operation was never recorded');

Schedule::command('payments:expire-checkouts')
    ->hourly()
    ->withoutOverlapping(30)
    ->onOneServer()
    ->description('Expire checkouts nobody finished');

/*
 * Hourly, so a warning goes out within the hour of entering the warning
 * window (config('payments.authorization_warning_hours')).
 */
Schedule::command('payments:check-authorizations')
    ->hourly()
    ->withoutOverlapping(30)
    ->onOneServer()
    ->description('Warn about payment holds nearing expiry and expire lapsed ones');

/*
 * Hourly: a PayPal or Demo subscription cancelled at period end is ended
 * within the hour after its paid period runs out.
 */
Schedule::command('payments:end-subscriptions')
    ->hourly()
    ->withoutOverlapping(30)
    ->onOneServer()
    ->description('End subscriptions cancelled at period end, and expire unfinished subscription checkouts');

Schedule::command('payments:notify-trials-ending')
    ->daily()
    ->withoutOverlapping(60)
    ->onOneServer()
    ->description('Remind subscribers whose free trial ends soon');

Schedule::command('payments:prune-webhook-events')
    ->daily()
    ->withoutOverlapping(60)
    ->onOneServer()
    ->description('Delete webhook events past their retention period');
