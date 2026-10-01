<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for booking requests.
 *
 * There is no `create` permission: requests come from the public form. `update`
 * covers the status changes, which hold and release seats, so it is a sales
 * decision rather than a content one.
 */
class TripInquiryPolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewTripInquiries,
            'view' => Permission::ViewTripInquiries,
            'update' => Permission::ManageTripInquiries,
            'delete' => Permission::ManageTripInquiries,
        ];
    }
}
