<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for print orders in the admin panel.
 *
 * Deliberately no create, update or delete: an order is what the customer's
 * phone sent, and the panel's job is to see it -- the fulfillment console
 * (guarded by FulfillPrintOrders) is where orders are worked, through the
 * status transitions PrintOrder::transitionTo() allows.
 */
class PrintOrderPolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewPrintOrders,
            'view' => Permission::ViewPrintOrders,
        ];
    }
}
