<?php

namespace App\Console\Commands;

use App\Auth\DevLoginAccounts;
use Illuminate\Console\Command;

/**
 * Audit the running configuration for settings that are unsafe once deployed.
 *
 * .env.production.example makes the safe values the starting point, but an
 * example file only helps the person who copies it. This checks the
 * configuration the application has ACTUALLY loaded, which is the only thing
 * that governs behaviour -- env vars injected by the platform, a stale .env
 * carried over from a previous deploy, and a cached config file that no longer
 * matches either.
 *
 * Intended as a deploy step: it exits non-zero on a failure, so a pipeline
 * that runs it stops before the instance takes traffic.
 *
 * Every check reads config() rather than env(). Once `config:cache` has run,
 * env() returns null for everything outside the cached file -- so a check
 * written against env() would report a perfectly configured instance as
 * broken, and a broken one as fine.
 */
class CheckProductionConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-production
                            {--warnings-as-errors : Exit non-zero on warnings as well as failures}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the loaded configuration for settings that are unsafe in a deployed environment';

    /**
     * Failures found. A failure is unsafe in every deployment.
     *
     * @var list<string>
     */
    private array $failures = [];

    /**
     * Warnings found. A warning is a supported choice with a real cost.
     *
     * @var list<string>
     */
    private array $warnings = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Checks the safe environments by name rather than excluding
        // 'production'. APP_ENV is overridable on the Dokploy deploy, so a
        // staging instance -- which owns a real database and is reachable --
        // must be audited exactly as production is. The same reasoning governs
        // DB::prohibitDestructiveCommands(); see .ai/rules/dev-login.md.
        if (app()->environment(['local', 'testing'])) {
            $this->components->info('Environment is ['.app()->environment().']; these checks apply to deployed environments only.');

            return self::SUCCESS;
        }

        $this->checkApplication();
        $this->checkSessions();
        $this->checkMail();
        $this->checkStorage();
        $this->checkDevelopmentAccess();

        return $this->report();
    }

    /**
     * Check the settings that expose the application itself.
     */
    private function checkApplication(): void
    {
        if (config('app.debug')) {
            $this->recordFailure('APP_DEBUG is true: stack traces, environment variables and database credentials are rendered to any visitor who triggers an error.');
        }

        if (blank(config('app.key'))) {
            $this->recordFailure('APP_KEY is empty: sessions and encrypted values cannot be decrypted. Generate one with `php artisan key:generate`.');
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $this->recordWarning('APP_URL is not https: signed URLs, password-reset links and mailed links are generated with the scheme named here, whatever the browser used.');
        }

        // '*' trusts the forwarded headers of whatever reached the container.
        // That is correct behind a proxy on an unpublished port, and a spoofing
        // hole the moment the port is reachable directly -- which this cannot
        // tell from inside, hence a warning rather than a failure.
        if (config('trustedproxy.proxies') === '*') {
            $this->recordWarning('TRUST_PROXIES is "*": safe only while nothing but the reverse proxy can reach this application. If its port is published, name the proxy addresses instead.');
        }

        if (config('logging.default') === 'stack' && in_array('single', (array) config('logging.channels.stack.channels'), true)) {
            $this->recordWarning('The log stack writes to the "single" channel: nothing rotates it, so the file grows until it fills the disk. Use "daily" with LOG_DAILY_DAYS.');
        }

        if (config('logging.level', 'debug') === 'debug') {
            $this->recordWarning('LOG_LEVEL is debug: request payloads and query bindings are written to disk, which can include credentials and personal data.');
        }
    }

    /**
     * Check the session settings that protect a signed-in user.
     */
    private function checkSessions(): void
    {
        if (config('session.secure') === false) {
            $this->recordWarning('SESSION_SECURE_COOKIE is false: the session cookie is sent over plain HTTP, where it can be read in transit. Set it true on an https-only site.');
        }

        if (! config('session.encrypt')) {
            $this->recordWarning('SESSION_ENCRYPT is false: session payloads are stored readable, so a database dump or backup exposes live sessions.');
        }

        // The database driver is this stack's default and needs no warning;
        // the file driver is what breaks silently, because each container gets
        // its own disk and a user is logged out whenever the load balancer
        // sends them to a different one.
        if (config('session.driver') === 'file') {
            $this->recordWarning('SESSION_DRIVER is file: sessions live on one container\'s disk, so they are lost on redeploy and not shared between instances.');
        }
    }

    /**
     * Check that mail will actually be delivered.
     */
    private function checkMail(): void
    {
        $mailer = config('mail.default');

        // 'log' is the local default in .env.example, and the failure it
        // produces is entirely silent: password resets and verification mail
        // are written to the log file, and the user simply never receives one.
        if (in_array($mailer, ['log', 'array'], true)) {
            $this->recordFailure("MAIL_MAILER is [{$mailer}]: mail is not delivered, so password resets and email verification silently never arrive.");
        }

        if (blank(config('mail.from.address'))) {
            $this->recordFailure('MAIL_FROM_ADDRESS is empty: outgoing mail has no sender and is likely to be rejected.');
        }
    }

    /**
     * Check the queue and storage settings that survive a redeploy.
     */
    private function checkStorage(): void
    {
        // Not a failure: an installation with no worker is a legitimate
        // configuration. It is a warning because the cost is invisible --
        // uploads process inline, so a request waits for image encoding.
        if (config('queue.default') === 'sync') {
            $this->recordWarning('QUEUE_CONNECTION is sync: queued work runs inside the web request, so uploads and mail block the response and a failure surfaces as a request error.');
        }

        if (config('cache.default') === 'array') {
            $this->recordFailure('CACHE_STORE is array: the cache is discarded at the end of every request, so rate limiter state and scheduled-task locks do not persist.');
        }

        if (config('filesystems.default') === 'local') {
            $this->recordWarning('FILESYSTEM_DISK is local: uploaded files live inside the container and are lost on redeploy unless that path is a mounted volume.');
        }
    }

    /**
     * Check that development affordances are switched off.
     */
    private function checkDevelopmentAccess(): void
    {
        // The one-click logins use fixed, publicly known credentials from
        // config/first.php. The gate is a denylist of 'production' alone, so
        // every other deployed environment name -- staging, demo, review-42 --
        // has them on. That is a deliberate project decision rather than a
        // bug (see .ai/rules/dev-login.md), which is why this is a warning:
        // its job is to make sure the decision was a decision.
        if (app(DevLoginAccounts::class)->enabled()) {
            $this->recordWarning('Passwordless dev login is enabled in ['.app()->environment().']: anyone reaching this instance can sign in as the seeded accounts, whose credentials are public. Only APP_ENV=production disables it.');
        }
    }

    /**
     * Record a setting that is unsafe in every deployment.
     */
    private function recordFailure(string $message): void
    {
        $this->failures[] = $message;
    }

    /**
     * Record a supported setting whose cost should be a deliberate choice.
     */
    private function recordWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * Print what was found and choose the exit code.
     */
    private function report(): int
    {
        foreach ($this->failures as $failure) {
            $this->components->error($failure);
        }

        foreach ($this->warnings as $warning) {
            $this->components->warn($warning);
        }

        if ($this->failures === [] && $this->warnings === []) {
            $this->components->info('Configuration looks correct for a deployed environment.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->info(sprintf(
            'Found %d failure(s) and %d warning(s).',
            count($this->failures),
            count($this->warnings),
        ));

        if ($this->failures !== []) {
            return self::FAILURE;
        }

        return $this->option('warnings-as-errors') ? self::FAILURE : self::SUCCESS;
    }
}
