<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for order inquiries in the admin panel.
 *
 * There is deliberately no `create` permission: inquiries arrive from the
 * public order form, which authorizes nobody, and the panel has no business
 * manufacturing one. `update` covers the baker's side only -- status, quote
 * and notes -- never what the customer asked for.
 */
class OrderInquiryPolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewOrderInquiries,
            'view' => Permission::ViewOrderInquiries,
            'update' => Permission::UpdateOrderInquiries,
            'delete' => Permission::DeleteOrderInquiries,
        ];
    }
}
