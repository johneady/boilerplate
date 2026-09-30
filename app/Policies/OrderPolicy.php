<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for customer orders in the admin panel.
 *
 * There is no `create`: orders come from customers checking out on the
 * website. There is no `delete` either -- an order that should not happen is
 * cancelled, which keeps it on record.
 */
class OrderPolicy extends BasePolicy
{
    /**
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewOrders,
            'view' => Permission::ViewOrders,
            'update' => Permission::ManageOrders,
        ];
    }
}
