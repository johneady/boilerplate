<?php

namespace App\Notifications;

use App\Settings\Settings;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Warns the operator that a queued job exhausted its attempts.
 *
 * Deliberately NOT a ShouldQueue notification. This announces that the queue
 * has just failed to run something; handing it to that same queue is how an
 * alert about a broken worker ends up sitting unsent in the table it is
 * warning about. It is sent inline from the failure listener instead, which
 * costs the worker one SMTP round trip on a path that only runs when
 * something is already wrong.
 *
 * The message carries no job payload. A payload holds whatever the job was
 * dispatched with -- model attributes, tokens, addresses -- and this is
 * unencrypted email to an address configured in the admin panel, so it names
 * the job and the error and leaves the rest for `queue:failed`.
 */
class QueueJobFailed extends Notification
{
    public function __construct(
        private readonly string $jobName,
        private readonly string $connection,
        private readonly string $queue,
        private readonly string $errorMessage,
    ) {}

    /**
     * The delivery channels for this notification.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $businessName = app(Settings::class)->businessName();

        return (new MailMessage)
            ->error()
            // Without an explicit greeting, ->error() renders the framework's
            // default "Whoops!" header, which reads as an apology to a
            // customer rather than a status report to whoever is on call.
            ->greeting(__('Background job failed'))
            ->subject(__('Background job failed on :business', ['business' => $businessName]))
            ->line(__('A queued background job failed after exhausting its retries and will not run again on its own.'))
            ->line(__('Job: :job', ['job' => $this->jobName]))
            ->line(__('Connection: :connection', ['connection' => $this->connection]))
            ->line(__('Queue: :queue', ['queue' => $this->queue]))
            ->line(__('Error: :error', ['error' => $this->errorMessage]))
            ->line(__('Run `php artisan queue:failed` on the server for the full payload and stack trace, and `php artisan queue:retry` to run it again once the cause is fixed.'))
            ->line(__('Further alerts for this job are held back briefly so a run of failures cannot flood this inbox.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array{job: string, connection: string, queue: string, error: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'job' => $this->jobName,
            'connection' => $this->connection,
            'queue' => $this->queue,
            'error' => $this->errorMessage,
        ];
    }
}
