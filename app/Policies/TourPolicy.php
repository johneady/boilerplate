<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for tours and their departures in the admin panel.
 */
class TourPolicy extends BasePolicy
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
