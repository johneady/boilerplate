<?php

namespace App\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single place an audit entry is written.
 *
 * Every source -- the Auditable trait, the authentication listener, the
 * settings writer -- goes through here rather than calling AuditLog::create()
 * itself, so actor resolution, request context, redaction and failure handling
 * are decided once. A second write path is how a trail ends up with entries
 * that name no actor, or that captured a password because that site forgot to
 * filter.
 *
 * Bound as a scoped instance in AppServiceProvider for the same reason
 * App\Settings\Settings is: a singleton would hold a resolved actor for a
 * queue worker's whole lifetime, attributing every job's writes to whoever
 * happened to be authenticated when the worker booted.
 */
class AuditLogger
{
    /**
     * Whether recording is switched on for this process.
     *
     * Read once per instance rather than per write. The scoped binding is
     * rebuilt between jobs and between requests, so this still follows a
     * configuration change without holding a stale value for a worker's life.
     */
    private readonly bool $enabled;

    /**
     * Attribute names that must never be written, whatever the caller passes.
     *
     * @var list<string>
     */
    private readonly array $neverRecord;

    public function __construct()
    {
        $this->enabled = (bool) config('audit.enabled', true);

        /** @var list<string> $neverRecord */
        $neverRecord = config('audit.never_record', []);
        $this->neverRecord = $neverRecord;
    }

    /**
     * Record a change made to a model record.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function recordModelEvent(
        AuditEvent $event,
        Model $record,
        array $oldValues = [],
        array $newValues = [],
    ): ?AuditLog {
        $old = $this->redact($oldValues);
        $new = $this->redact($newValues);

        // Redaction runs BEFORE the decision to write, not after it. An update
        // whose every changed column is on the denylist -- a password change, a
        // two-factor secret being written, the remember_token Laravel
        // regenerates on every remember-me sign-in -- redacts down to nothing,
        // and an entry reading "User updated" with no before and no after says
        // less than no entry at all while still taking a row in the
        // fastest-growing table here. The caller cannot make this call itself:
        // its change set is non-empty at the point it checks.
        if ($event === AuditEvent::Updated && $old === [] && $new === []) {
            return null;
        }

        return $this->write($event, [
            'auditable_type' => $record->getMorphClass(),
            'auditable_id' => $record->getKey(),
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }

    /**
     * Record something that happened without a model behind it.
     *
     * Authentication events and settings changes both land here: the context
     * array carries whatever is worth knowing (the email a failed sign-in used,
     * the setting keys that changed) in place of a before/after diff.
     *
     * @param  array<string, mixed>  $context
     */
    public function record(AuditEvent $event, array $context = [], ?User $actor = null): ?AuditLog
    {
        return $this->write($event, [
            'context' => $this->redact($context) ?: null,
        ], $actor);
    }

    /**
     * Persist an entry, resolving the actor and request context around it.
     *
     * Never allowed to break the thing it is recording. An audit write failing
     * -- a migration not yet run on a half-deployed instance, a full disk --
     * must not turn a successful password change into a 500 for the user, so
     * the failure is logged and swallowed. That trade is deliberate and is the
     * reverse of the usual advice: this application's trail is for
     * accountability, not for regulatory non-repudiation. A deployment that
     * needs writes to fail closed should rethrow here.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function write(AuditEvent $event, array $attributes, ?User $actor = null): ?AuditLog
    {
        if (! $this->enabled) {
            return null;
        }

        $actor ??= $this->currentActor();

        try {
            $entry = new AuditLog;

            $entry->forceFill([
                'event' => $event,
                'user_id' => $actor?->getKey(),
                // Copied, not joined: the foreign key goes null when the
                // account is deleted, and an entry that cannot name its actor
                // is barely an audit entry at all.
                'user_name' => $actor?->name,
                'user_email' => $actor?->email,
                'ip_address' => $this->requestIp(),
                'user_agent' => $this->requestUserAgent(),
                ...$attributes,
            ])->save();

            return $entry;
        } catch (Throwable $e) {
            Log::warning('Failed to write audit log entry.', [
                'event' => $event->value,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * The authenticated user, when there is one.
     *
     * Resolved through the guard rather than a cached property so a sign-in
     * during the same request is attributed to the account that just
     * authenticated, not to the guest that started it.
     */
    private function currentActor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Whether a real HTTP request is what triggered this write.
     *
     * app()->bound('request') is NOT the check, though it reads like it: the
     * container always has a `request` binding, console included, where Laravel
     * synthesises one from argv -- REMOTE_ADDR and all. Asking it there answers
     * `127.0.0.1` and `Symfony`, a fabricated client for a scheduled command or
     * a queued job that had no client at all. That is worse than recording
     * nothing, because an administrator reading the trail cannot tell it from a
     * genuine localhost request.
     *
     * A RESOLVED ROUTE is the discriminator. The router sets it while handling
     * an HTTP request and nothing sets it on the synthesised console one, so
     * this is false for an artisan command, a scheduled run and a queued job --
     * including a job dispatched from a request, which executes later in a
     * worker with a request of its own that was never routed. The server
     * variables cannot tell these apart; this can.
     */
    private function hasHttpRequest(): bool
    {
        return app()->bound('request') && request()->route() !== null;
    }

    /**
     * The client address, when a request is what triggered this.
     *
     * Null in the console: a scheduled command, an artisan run or a queued job
     * has no client to name.
     */
    private function requestIp(): ?string
    {
        return $this->hasHttpRequest() ? request()->ip() : null;
    }

    /**
     * The client user agent, truncated to the column width.
     *
     * Trimmed here rather than relying on the database: MySQL in strict mode
     * rejects an over-long value outright, which would lose the entry to the
     * catch in write() over something entirely cosmetic.
     */
    private function requestUserAgent(): ?string
    {
        if (! $this->hasHttpRequest()) {
            return null;
        }

        $agent = request()->userAgent();

        return $agent === null ? null : mb_substr($agent, 0, 255);
    }

    /**
     * Strip attributes that must never be recorded.
     *
     * Applied to every array on its way in, whatever the source claims to have
     * filtered already. The Auditable trait excludes its own sensitive columns,
     * but this is the backstop that means a new write site, or a new model
     * whose author forgot, cannot leak a secret into a table every
     * administrator can read.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        return array_diff_key($values, array_flip($this->neverRecord));
    }
}
