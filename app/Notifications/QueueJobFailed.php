<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

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
 *
 * The "deliberately not queued" note above is why this extends
 * BaseNotification without implementing ShouldQueue -- the base class is
 * unqueued by default precisely so this stays a decision rather than a default
 * someone has to remember to undo.
 */
class QueueJobFailed extends BaseNotification
{
    public function __construct(
        private readonly string $jobName,
        private readonly string $connection,
        private readonly string $queue,
        private readonly string $errorMessage,
    ) {}

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return $this->mailMessage(__('Background job failed'))
            ->error()
            // Without an explicit greeting, ->error() renders the framework's
            // default "Whoops!" header, which reads as an apology to a
            // customer rather than a status report to whoever is on call.
            ->greeting(__('Background job failed'))
            ->line(__('A queued background job failed after exhausting its retries and will not run again on its own.'))
            // The specifics go in a Markdown table rather than in the run of
            // prose: this is the part someone on call scans first, and the
            // HTML mail renders it as a bordered table while the plain text
            // part stays readable as aligned rows. Building the same thing
            // out of raw <table> HTML instead collapses to one unbroken run
            // of words in the text part, which is the version that matters
            // most when the alert is read on a phone.
            ->line(new HtmlString($this->detailsTable()))
            ->line(__('Run `php artisan queue:failed` on the server for the full payload and stack trace, and `php artisan queue:retry` to run it again once the cause is fixed.'))
            ->line(__('Further alerts for this job are held back briefly so a run of failures cannot flood this inbox.'));
    }

    /**
     * The failure's specifics as a Markdown table.
     *
     * Values are wrapped in code spans so a class name or an error message
     * containing Markdown punctuation renders literally, and any pipe is
     * escaped so it cannot break out of its cell.
     */
    private function detailsTable(): string
    {
        $rows = [
            __('Job') => $this->jobName,
            __('Connection') => $this->connection,
            __('Queue') => $this->queue,
            __('Error') => $this->errorMessage,
        ];

        $lines = [
            '| '.__('Detail').' | '.__('Value').' |',
            '| :--- | :--- |',
        ];

        foreach ($rows as $label => $value) {
            $lines[] = sprintf('| %s | %s |', $label, $this->codeSpan($value));
        }

        // Wrapped exactly as the framework's x-mail::table component does: the
        // theme styles table cells through a .table ancestor, and a bare
        // Markdown table renders with no class at all, so without this div the
        // rows inherit none of the borders, spacing or wrapping below.
        return '<div class="table">'."\n\n".implode("\n", $lines)."\n\n".'</div>';
    }

    /**
     * A value wrapped in a code span no backtick inside it can close.
     *
     * A code span ends at the next backtick RUN of the same length as the one
     * that opened it, so wrapping in single backticks lets an error message
     * containing one hand everything after it to the Markdown parser -- an
     * attacker-influenced exception message becomes a clickable link in the
     * operator's alert. The delimiter is one backtick longer than the longest
     * run inside the value, and the spaces either side stop a value that
     * starts or ends with a backtick from touching the delimiters.
     *
     * Newlines are collapsed first: a table row is one line in GFM, so a
     * newline would end the row mid-span and hand the rest of the message to
     * the parser as a fresh row. Code spans render newlines as spaces anyway,
     * so nothing is lost.
     */
    private function codeSpan(string $value): string
    {
        $value = (string) preg_replace('/\R+/u', ' ', $value);

        $longestRun = 0;

        if (preg_match_all('/`+/', $value, $runs) > 0) {
            foreach ($runs[0] as $run) {
                $longestRun = max($longestRun, strlen($run));
            }
        }

        $delimiter = str_repeat('`', $longestRun + 1);

        return sprintf('%s %s %s', $delimiter, str_replace('|', '\|', $value), $delimiter);
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
