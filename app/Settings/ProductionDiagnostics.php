<?php

namespace App\Settings;

/**
 * Audit the resolved configuration for settings that are unsafe in production.
 *
 * Reads the *resolved* config rather than raw env, so what is reported is what
 * the application actually runs with -- a value overridden in a config file,
 * or cached by `config:cache` from a since-changed env, is caught where an
 * env-file check would miss it.
 *
 * Findings are graded by DiagnosticSeverity. An Error is wrong in every
 * production deployment; a Warning is a value that may be deliberate in
 * context, because a panel that painted everything red would teach an
 * administrator to ignore all of it.
 *
 * Deliberately environment-aware rather than environment-gated: the checks run
 * in any environment so the panel is useful while developing, but the ones
 * that only matter once deployed (a SQLite file, a log mailer) are raised only
 * when the environment claims to be production. That keeps a local machine
 * from showing a wall of findings that are all correct locally.
 */
class ProductionDiagnostics
{
    /**
     * Run every check against the current configuration.
     *
     * @return list<DiagnosticResult>
     */
    public function run(): array
    {
        $environment = (string) config('app.env');
        $isProduction = $environment === 'production';

        $appUrl = (string) config('app.url');
        $isHttps = str_starts_with($appUrl, 'https://');

        return [
            $this->check(
                'Debug mode',
                ! config('app.debug'),
                DiagnosticSeverity::Error,
                'Debug mode is on. Any error renders a stack trace, configuration values and recent queries to the visitor who triggered it.',
                'Debug mode is off, so errors render the styled error pages instead of a stack trace.',
            ),

            $this->check(
                'Application key',
                filled(config('app.key')),
                DiagnosticSeverity::Error,
                'No application key is set, so sessions, cookies and every encrypted column cannot be decrypted.',
                'An application key is set.',
            ),

            $this->check(
                'Database driver',
                ! $isProduction || config('database.default') !== 'sqlite',
                DiagnosticSeverity::Error,
                'SQLite is in use. The database file is lost whenever the container is replaced, and concurrent writes block one another.',
                'A server-backed database connection is in use.',
            ),

            // Same failure as SQLite above, one directory across: the "local"
            // and "public" disks both write inside the container, and the
            // Dokploy stack mounts a volume for storage/logs only. Uploaded
            // avatars and the site logo therefore live on the container's own
            // layer and are destroyed by the next deploy, with nothing in the
            // application to notice. A remote driver (s3 and friends) or a
            // disk the operator has mounted a volume for is the fix.
            $this->check(
                'Image storage',
                ! $isProduction || ! in_array($this->imageDiskDriver(), ['local', null], true),
                DiagnosticSeverity::Warning,
                'Processed images are written to a container-local disk. Unless a volume is mounted over it, every uploaded avatar and the site logo are lost when the container is replaced.',
                'Processed images are written to a disk outside the container.',
            ),

            $this->check(
                'Session encryption',
                ! $isProduction || (bool) config('session.encrypt'),
                DiagnosticSeverity::Error,
                'Session payloads are stored unencrypted, so anyone able to read the session table can read their contents.',
                'Session payloads are encrypted at rest.',
            ),

            // Two different reasons to pass, so the passing message must say
            // which: "restricted to https" would be a lie on an http site,
            // where the check is simply inapplicable.
            $this->check(
                'Secure session cookie',
                ! $isHttps || (bool) config('session.secure'),
                DiagnosticSeverity::Error,
                'The site is served over https but the session cookie is not marked secure, so a browser will also send it over plain http.',
                $isHttps
                    ? 'The session cookie is restricted to https.'
                    : 'Not applicable while the site is served over plain http.',
            ),

            $this->check(
                'Application URL',
                ! $isProduction || ($isHttps && ! str_contains($appUrl, 'localhost')),
                DiagnosticSeverity::Error,
                "The application URL is [{$appUrl}]. Signed URLs, password reset links and queued mail are all built from it.",
                "Signed URLs and mailed links are built from [{$appUrl}].",
            ),

            // '*' is correct on a platform whose proxy is the only route in
            // (the Dokploy stack sets exactly that), and wrong the moment a
            // container port is published to the host: a direct caller then
            // spoofs X-Forwarded-For freely. PHP cannot see Docker's port
            // bindings, so this can only ever be raised as a question.
            $this->check(
                'Trusted proxies',
                ! $isProduction || config('trustedproxy.proxies') !== '*',
                DiagnosticSeverity::Warning,
                'Every proxy is trusted. That is safe only while no container port is published directly to the host -- check the ports in your compose file.',
                'A specific set of proxies is trusted.',
            ),

            $this->check(
                'Mail delivery',
                ! $isProduction || config('mail.default') !== 'log',
                DiagnosticSeverity::Warning,
                'Mail is written to the application log rather than sent, so password resets and verification links never reach anyone.',
                'Mail is delivered through a real mailer.',
            ),

            // config/fortify.php defaults the secret to app.key, so the
            // resolved value is never empty and cannot itself distinguish
            // "chosen" from "fell back" -- the config file records which it
            // was. Falling back is the risk: the WebAuthn user handle then
            // derives from APP_KEY, and rotating that key invalidates every
            // registered passkey at once.
            $this->check(
                'Passkey handle secret',
                ! $isProduction || (bool) config('fortify.passkeys.has_dedicated_user_handle_secret'),
                DiagnosticSeverity::Warning,
                'No dedicated secret is set, so passkey user handles derive from the application key. Rotating that key would invalidate every registered passkey.',
                'Passkey user handles derive from their own secret, so they survive an application key rotation.',
            ),

            $this->check(
                'Log level',
                ! $isProduction || ! in_array('debug', $this->activeLogLevels(), true),
                DiagnosticSeverity::Warning,
                'Debug-level logging is active. It fills the disk quickly and records request detail you may not want retained.',
                'Logging is set above debug level.',
            ),
        ];
    }

    /**
     * The findings that did not pass, most severe first.
     *
     * @return list<DiagnosticResult>
     */
    public function failures(): array
    {
        $failures = array_values(array_filter(
            $this->run(),
            fn (DiagnosticResult $result): bool => ! $result->passed,
        ));

        usort(
            $failures,
            fn (DiagnosticResult $a, DiagnosticResult $b): int => ($a->severity === DiagnosticSeverity::Error ? 0 : 1)
                <=> ($b->severity === DiagnosticSeverity::Error ? 0 : 1),
        );

        return $failures;
    }

    /**
     * The driver backing the disk processed images are written to.
     *
     * Reported as the driver rather than the disk name so a project that
     * renames its disk, or points IMAGE_DISK at one of its own, is judged on
     * where the files actually go.
     */
    private function imageDiskDriver(): ?string
    {
        $disk = (string) config('images.disk');

        $driver = config("filesystems.disks.{$disk}.driver");

        return is_string($driver) ? $driver : null;
    }

    /**
     * The levels of every channel the active logging stack writes through.
     *
     * There is no top-level logging.level key -- each channel carries its own
     * -- so the stack's channels are resolved and their levels collected. A
     * non-stack default channel is read directly.
     *
     * @return list<string>
     */
    private function activeLogLevels(): array
    {
        $default = (string) config('logging.default');

        /** @var array<string, mixed> $channels */
        $channels = config('logging.channels', []);

        /** @var array<string, mixed> $channel */
        $channel = $channels[$default] ?? [];

        $names = ($channel['driver'] ?? null) === 'stack'
            ? (array) ($channel['channels'] ?? [])
            : [$default];

        return array_values(array_filter(array_map(
            function ($name) use ($channels): ?string {
                $level = $channels[(string) $name]['level'] ?? null;

                return is_string($level) ? $level : null;
            },
            $names,
        )));
    }

    /**
     * Build the result for one check.
     *
     * The failing severity is passed in rather than stored on the result of a
     * passing check, so a check reads as a single statement of what "good"
     * looks like and how badly it matters when it is not met.
     */
    private function check(
        string $name,
        bool $passed,
        DiagnosticSeverity $severity,
        string $failureDetail,
        string $passedDetail,
    ): DiagnosticResult {
        return new DiagnosticResult(
            name: $name,
            passed: $passed,
            severity: $passed ? DiagnosticSeverity::Passed : $severity,
            detail: $passed ? $passedDetail : $failureDetail,
        );
    }
}
