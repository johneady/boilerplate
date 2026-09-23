<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for the bakery menu in the admin panel.
 *
 * The public menu needs no ability: it lists available items to everyone.
 */
class MenuItemPolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewMenuItems,
            'view' => Permission::ViewMenuItems,
            'create' => Permission::CreateMenuItems,
            'update' => Permission::UpdateMenuItems,
            'delete' => Permission::DeleteMenuItems,
        ];
    }
}
