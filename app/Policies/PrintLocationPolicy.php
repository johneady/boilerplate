<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for print locations in the admin panel.
 *
 * A location is counter signage: its row decides which URL the store's QR
 * codes encode. Managing it is guarded by one permission for every ability --
 * anyone trusted to add a counter is trusted to retire one.
 */
class PrintLocationPolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ManagePrintLocations,
            'view' => Permission::ManagePrintLocations,
            'create' => Permission::ManagePrintLocations,
            'update' => Permission::ManagePrintLocations,
            'delete' => Permission::ManagePrintLocations,
        ];
    }
}
