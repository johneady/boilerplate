<?php

namespace App\Listeners;

use App\Audit\AuditEvent;
use App\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Writes the authentication half of the audit trail.
 *
 * Subscribed to Illuminate's own auth events rather than hooked into Fortify's
 * controllers, so every path that authenticates is covered by construction --
 * the login form, a passkey, the two-factor challenge, the dev-login shortcut,
 * and anything added later. Hooking the controllers would cover only the paths
 * somebody remembered.
 *
 * Handled synchronously rather than queued: a sign-in that is recorded only if
 * a worker is running is not a trail. The write is one insert, and
 * AuditLogger swallows its own failures.
 *
 * Registration is left entirely to Laravel's listener discovery, which scans
 * app/Listeners and binds EVERY method whose name starts with "handle" to the
 * event it type-hints -- not just a lone handle(). Adding an explicit
 * Event::listen() for these in a service provider therefore registers each one
 * a SECOND time, and every sign-in is written twice. That is not a visible
 * error: the trail simply doubles, which reads as a bug in the application
 * being audited rather than in the audit. Verified with `php artisan
 * event:list`, which is how to check after touching this.
 */
class RecordAuthenticationAudit
{
    public function __construct(private readonly AuditLogger $logger) {}

    /**
     * Record a successful sign-in.
     *
     * The actor is passed explicitly rather than left to the logger's guard
     * lookup: on a stateless guard, and during the request that authenticates,
     * Auth::user() is not reliably populated yet when this fires.
     */
    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->logger->record(AuditEvent::Login, [
            'guard' => $event->guard,
            'remember' => $event->remember,
        ], $event->user);
    }

    /**
     * Record a sign-out.
     *
     * Logout fires with a null user for a guest session being flushed, which is
     * not an event worth a row.
     */
    public function handleLogout(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->logger->record(AuditEvent::Logout, [
            'guard' => $event->guard,
        ], $event->user);
    }

    /**
     * Record a rejected sign-in attempt.
     *
     * This is the event the trail exists for -- a run of these against one
     * address is what a brute-force attempt looks like from the inside. The
     * attempted identifier is recorded; the credentials are NOT, since
     * $event->credentials holds the submitted password in plain text.
     */
    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        $this->logger->record(AuditEvent::LoginFailed, [
            'guard' => $event->guard,
            'email' => is_string($email) ? $email : null,
            // Distinguishes "wrong password for a real account" from "no such
            // account", which read very differently in a burst.
            'account_exists' => $event->user !== null,
        ], $event->user instanceof User ? $event->user : null);
    }

    /**
     * Record a completed password reset.
     *
     * Beside the PasswordChanged notification rather than instead of it: the
     * notification warns the account holder, this tells an administrator
     * reading the trail six months later that it happened.
     */
    public function handlePasswordReset(PasswordReset $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->logger->record(AuditEvent::PasswordReset, [], $event->user);
    }
}
