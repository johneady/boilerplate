<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\User;

/**
 * Authorization for reading the audit trail.
 *
 * Read-only on purpose, and more strictly than the other policies: `create`,
 * `update` and `delete` are left unmapped, so BasePolicy denies them to
 * everyone -- and delete() below denies them to administrators too, who would
 * otherwise pass through the Gate::before bypass.
 *
 * That is the whole point of an audit log. A trail an administrator can edit
 * or selectively delete records only what the administrator is willing to
 * admit to, which is worth less than no trail at all, because it still reads
 * as authoritative. Entries leave through retention alone
 * (app:prune-audit-log), which is time-based and blind to their content.
 *
 * Granting deletion later means more than adding a permission here: the bypass
 * exemption in AuthServiceProvider::SELF_PROTECTED_ABILITIES is what makes
 * this denial hold for administrators at all.
 */
class AuditLogPolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * Viewing only. Nothing creates an entry through the Gate -- entries are
     * written by App\Audit\AuditLogger, which authorizes nobody because it
     * records what already happened.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewAuditLog,
            'view' => Permission::ViewAuditLog,
        ];
    }

    /**
     * Determine whether the user may create an audit entry.
     *
     * Nobody may. Entries are written by App\Audit\AuditLogger as a record of
     * something that already happened; a hand-written one would be a forgery.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user may edit an audit entry.
     *
     * Nobody may. An editable trail records what the editor is willing to
     * admit to, while still reading as authoritative.
     */
    public function update(User $user, mixed $model = null): bool
    {
        return false;
    }

    /**
     * Determine whether the user may delete an audit entry.
     *
     * Nobody may, including administrators. Stated explicitly rather than left
     * to the unmapped default because the default is only reached for
     * non-administrators: Gate::before answers `delete` with true for an admin
     * unless the ability is exempted, and this policy is never consulted.
     * AuthServiceProvider exempts it for this model's sake.
     *
     * $model is optional because the Gate calls a policy method with no model
     * when the ability is checked against the CLASS -- `can('delete',
     * AuditLog::class)` -- which is the form Filament uses when deciding
     * whether to render a bulk delete action. Requiring it would make that
     * check fatal rather than denied.
     */
    public function delete(User $user, mixed $model = null): bool
    {
        return false;
    }

    /**
     * Determine whether the user may irreversibly delete an audit entry.
     *
     * Same rule, same reason.
     */
    public function forceDelete(User $user, mixed $model = null): bool
    {
        return false;
    }
}
