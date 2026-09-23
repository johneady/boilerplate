<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for orders in the admin panel.
 *
 * There is deliberately no `create` or `delete`: orders are created by
 * customers at checkout, and an order is a record of a sale that a refund
 * reverses rather than erases. `update` guards fulfilling and refunding.
 */
class OrderPolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewOrders,
            'view' => Permission::ViewOrders,
            'update' => Permission::UpdateOrders,
        ];
    }
}
