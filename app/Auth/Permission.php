<?php

namespace App\Auth;

/**
 * Every discrete thing a role may be granted.
 *
 * A permission is the unit policies and gates check, so a new capability is
 * added here and granted to roles in Role::permissions() -- not checked as a
 * role name at the call site. Checking roles directly is what makes an
 * authorization layer impossible to change later: `if ($user->isAdmin())`
 * scattered through the code has to be found and rewritten the day a third
 * role appears, while `$user->can(Permission::DeleteUsers)` keeps working.
 *
 * The backing values are namespaced strings ("users.delete") so they read
 * clearly in a Gate::before dump or a database column, and so an unrelated
 * permission cannot collide with them.
 */
enum Permission: string
{
    case ViewUsers = 'users.view';

    case CreateUsers = 'users.create';

    case UpdateUsers = 'users.update';

    case DeleteUsers = 'users.delete';

    case ManageSettings = 'settings.manage';

    case ViewLogs = 'logs.view';

    case AccessAdminPanel = 'admin-panel.access';

    /**
     * The label shown wherever a permission is listed for a human.
     */
    public function label(): string
    {
        return match ($this) {
            self::ViewUsers => 'View users',
            self::CreateUsers => 'Create users',
            self::UpdateUsers => 'Update users',
            self::DeleteUsers => 'Delete users',
            self::ManageSettings => 'Manage application settings',
            self::ViewLogs => 'View application logs',
            self::AccessAdminPanel => 'Access the admin panel',
        };
    }
}
