<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for the café's menu in the admin panel.
 *
 * One permission covers every ability: whoever looks after the menu adds,
 * edits, reorders and removes products alike.
 */
class ProductPolicy extends BasePolicy
{
    /**
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ManageProducts,
            'view' => Permission::ManageProducts,
            'create' => Permission::ManageProducts,
            'update' => Permission::ManageProducts,
            'delete' => Permission::ManageProducts,
        ];
    }
}
