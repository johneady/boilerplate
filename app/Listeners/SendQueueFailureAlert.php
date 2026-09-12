<?php

namespace App\Listeners;

use App\Notifications\QueueJobFailed;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Emails the operator when a queued job exhausts its retries.
 *
 * A failed_jobs row is only seen by someone who thinks to run `queue:failed`,
 * and App\Jobs\Job::failed() writes a log line nobody is watching either. This
 * is the push half: an address saved in the admin panel's mail settings gets
 * told, once, that something needs attention.
 *
 * Not registered when that address is blank -- an installation with nobody to
 * alert is a supported configuration, not a misconfiguration.
 */
class SendQueueFailureAlert
{
    /**
     * Handle the event.
     */
    public function handle(JobFailed $event): void
    {
        // Every step below reaches for the database: the settings table for
        // the address, the cache table for the throttle, and the mail config
        // the notification sends through. A job that failed BECAUSE the
        // database went away therefore fails this listener too -- and an
        // exception thrown here runs inside the worker's own failure
        // handling, where it can prevent the failed_jobs row being written
        // and lose the original error entirely.
        //
        // So alerting is strictly additive: anything that goes wrong in it is
        // caught and logged, leaving the queue's own failure handling and
        // App\Jobs\Job::failed()'s log line -- which needs no database --
        // exactly as they would have been.
        try {
            $this->alert($event);
        } catch (Throwable $exception) {
            Log::error('Failed to send queue failure alert.', [
                'job' => $event->job->resolveName(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Send the alert, unless there is nobody to alert or one was sent recently.
     */
    private function alert(JobFailed $event): void
    {
        $recipient = app(Settings::class)->string(SettingKey::OpsAlertEmail);

        if ($recipient === '') {
            return;
        }

        $jobName = $event->job->resolveName();

        if (! $this->shouldAlertFor($jobName)) {
            return;
        }

        Notification::route('mail', $recipient)->notify(new QueueJobFailed(
            jobName: $jobName,
            connection: $event->connectionName,
            queue: $event->job->getQueue(),
            errorMessage: $event->exception->getMessage(),
        ));
    }

    /**
     * Claim this job class's alert window, or report that it is already taken.
     *
     * Cache::add() is atomic and only succeeds when the key is absent, so two
     * workers failing the same job class at the same moment send one email
     * between them rather than one each. A zero or negative window disables
     * throttling, which is what a test wanting every alert asks for.
     */
    private function shouldAlertFor(string $jobName): bool
    {
        $minutes = (int) config('queue.failure_alert_throttle_minutes');

        if ($minutes <= 0) {
            return true;
        }

        return Cache::add(
            'queue-failure-alert:'.sha1($jobName),
            true,
            now()->addMinutes($minutes),
        );
    }
}
