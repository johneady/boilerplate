<?php

use App\Jobs\Job;
use App\Notifications\QueueJobFailed;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->settings = app(Settings::class);
});

/**
 * Fire the event the queue raises when a job exhausts its retries.
 *
 * The real event carries the worker's job wrapper rather than the job class,
 * so the listener reads the name through resolveName() and the queue through
 * getQueue(). A double supplies both without needing a running worker.
 */
function failJob(string $jobName = 'App\Jobs\ProcessUploadedImage', string $queue = 'default', string $error = 'Connection refused'): void
{
    $job = Mockery::mock(QueueJobContract::class);
    $job->shouldReceive('resolveName')->andReturn($jobName);
    $job->shouldReceive('getQueue')->andReturn($queue);

    Event::dispatch(new JobFailed('database', $job, new RuntimeException($error)));
}

test('a failed job emails the address saved in the alert setting', function () {
    Notification::fake();
    $this->settings->set(SettingKey::OpsAlertEmail, 'ops@cromulent.test');

    failJob();

    Notification::assertSentOnDemand(
        QueueJobFailed::class,
        fn (QueueJobFailed $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'ops@cromulent.test'
            && $channels === ['mail'],
    );
});

test('no alert is sent when no alert address is saved', function () {
    Notification::fake();

    // The unsaved default: an installation with nobody to alert is supported,
    // not a misconfiguration.
    expect($this->settings->string(SettingKey::OpsAlertEmail))->toBe('');

    failJob();

    Notification::assertNothingSent();
});

test('the alert names the job, queue and error so the log need not be opened', function () {
    Notification::fake();
    $this->settings->set(SettingKey::OpsAlertEmail, 'ops@cromulent.test');

    failJob(jobName: 'App\Jobs\ProcessUploadedImage', queue: 'images', error: 'SQLSTATE[HY000] server has gone away');

    Notification::assertSentOnDemand(
        QueueJobFailed::class,
        function (QueueJobFailed $notification): bool {
            $mail = $notification->toMail(new AnonymousNotifiable);

            $body = implode("\n", $mail->introLines);

            return str_contains($body, 'App\Jobs\ProcessUploadedImage')
                && str_contains($body, 'images')
                && str_contains($body, 'SQLSTATE[HY000] server has gone away');
        },
    );
});

test('a backtick in the error cannot inject markup into the alert', function () {
    Notification::fake();
    $this->settings->set(SettingKey::OpsAlertEmail, 'ops@cromulent.test');

    // An error message can embed user-influenced input. A code span is
    // delimited by a backtick RUN, so a single-backtick wrapper lets the
    // remainder be parsed as Markdown -- turning the operator's alert into a
    // phishing surface. The delimiter must out-long any run inside the value,
    // and a newline must not be allowed to end the table row mid-span either.
    $hostile = "a`b [urgent](https://evil.example) c`d\nand a second line [too](https://evil.example)";

    failJob(error: $hostile);

    Notification::assertSentOnDemand(
        QueueJobFailed::class,
        function (QueueJobFailed $notification): bool {
            $html = (string) $notification->toMail(new AnonymousNotifiable)->render();

            return str_contains($html, 'href="https://evil.example"') === false
                && str_contains($html, 'href="https://evil.example') === false
                // The message still reaches the operator, literally.
                && str_contains($html, '[urgent](https://evil.example)');
        },
    );
});

test('a second failure of the same job within the window sends no further alert', function () {
    Notification::fake();
    $this->settings->set(SettingKey::OpsAlertEmail, 'ops@cromulent.test');

    // The failure mode this guards: one expired credential fails every job in
    // the backlog within seconds, and an unthrottled alert sends hundreds of
    // identical emails.
    failJob();
    failJob();
    failJob();

    Notification::assertSentOnDemandTimes(QueueJobFailed::class, 1);
});

test('a different job failing within the same window still alerts', function () {
    Notification::fake();
    $this->settings->set(SettingKey::OpsAlertEmail, 'ops@cromulent.test');

    failJob(jobName: 'App\Jobs\ProcessUploadedImage');
    failJob(jobName: 'App\Jobs\SomeOtherJob');

    // Throttling is per job class, so a genuinely distinct failure during a
    // burst is never swallowed by the first one's window.
    Notification::assertSentOnDemandTimes(QueueJobFailed::class, 2);
});

test('the throttle window reopens once it has elapsed', function () {
    Notification::fake();
    $this->settings->set(SettingKey::OpsAlertEmail, 'ops@cromulent.test');

    failJob();

    $this->travel((int) config('queue.failure_alert_throttle_minutes') + 1)->minutes();

    failJob();

    Notification::assertSentOnDemandTimes(QueueJobFailed::class, 2);
});

test('a throttle window of zero alerts on every failure', function () {
    Notification::fake();
    Config::set('queue.failure_alert_throttle_minutes', 0);
    $this->settings->set(SettingKey::OpsAlertEmail, 'ops@cromulent.test');

    failJob();
    failJob();

    Notification::assertSentOnDemandTimes(QueueJobFailed::class, 2);
});

test('an alerting failure is logged rather than thrown, so the job failure is not masked', function () {
    Notification::fake();
    $this->settings->set(SettingKey::OpsAlertEmail, 'ops@cromulent.test');

    // Stands in for the database being the thing that broke: the listener
    // reaches for the cache to throttle, and the cache is on that database.
    Cache::shouldReceive('add')->andThrow(new RuntimeException('server has gone away'));

    Log::shouldReceive('error')
        ->once()
        ->with('Failed to send queue failure alert.', Mockery::on(
            fn (array $context): bool => $context['exception'] === 'server has gone away'
                && $context['job'] === 'App\Jobs\ProcessUploadedImage',
        ));

    failJob();

    Notification::assertNothingSent();
});
