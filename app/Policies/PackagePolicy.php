<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for managing the video packages in the admin panel.
 *
 * `update` covers stock and availability as well as the listing itself: the
 * panel adjusts both inline from the table, and splitting them would give a
 * role that can change a price but not correct a stock count, which nobody
 * has asked for.
 */
class PackagePolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewPackages,
            'view' => Permission::ViewPackages,
            'create' => Permission::CreatePackages,
            'update' => Permission::UpdatePackages,
            'delete' => Permission::DeletePackages,
        ];
    }
}
