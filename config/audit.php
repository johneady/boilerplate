<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    |
    | The trail of who changed what, written by App\Audit\AuditLogger and read
    | in the admin panel. Auditing is opt-in per model through the
    | App\Concerns\Auditable trait -- see .ai/rules/audit.md.
    |
    */

    /*
     * Master switch.
     *
     * Left ON in the test suite rather than disabled for speed. Auditing hooks
     * model events and the authentication events, so the suite exercising it
     * is what catches a trait that stopped recording or a listener that was
     * never registered -- both of which look exactly like a quiet instance.
     * The cost is one insert per model write against an in-memory database.
     *
     * Turn it off for an instance that must not record at all; a test needing
     * silence can set the config value for its own duration.
     */
    'enabled' => env('AUDIT_ENABLED', true),

    /*
     * How long entries are kept, in days.
     *
     * Pruned by app:prune-audit-log on the daily schedule. Zero or null keeps
     * entries forever, which is a deliberate choice rather than a default: the
     * table only grows, and nothing else in this application trims it.
     */
    'retention_days' => env('AUDIT_RETENTION_DAYS', 365),

    /*
     * Attributes never written to the audit log, on any model.
     *
     * Applied on top of whatever a model excludes itself, so a secret cannot
     * be captured merely because someone forgot to list it on a new model. The
     * audit table is read by every administrator and survives the record it
     * describes -- a password hash copied into it outlives the account.
     */
    'never_record' => [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ],

];
