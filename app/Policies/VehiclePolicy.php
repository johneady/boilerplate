<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for managing the car range in the admin panel.
 *
 * `update` covers prices and specifications along with the copy: they are
 * edited together on one form, and nobody has asked to split them.
 */
class VehiclePolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewVehicles,
            'view' => Permission::ViewVehicles,
            'create' => Permission::CreateVehicles,
            'update' => Permission::UpdateVehicles,
            'delete' => Permission::DeleteVehicles,
        ];
    }
}
