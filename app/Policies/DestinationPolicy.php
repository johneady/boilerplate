<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for destinations in the admin panel. The public pages need
 * none: every destination is public.
 */
class DestinationPolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ManageTours,
            'view' => Permission::ManageTours,
            'create' => Permission::ManageTours,
            'update' => Permission::ManageTours,
            'delete' => Permission::ManageTours,
        ];
    }
}
