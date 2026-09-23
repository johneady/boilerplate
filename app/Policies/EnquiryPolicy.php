<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for the customer enquiries (the CRM) in the admin panel.
 *
 * No `create`: an enquiry is what a customer sent, entered through the public
 * forms only. `update` covers the sales team's own fields -- status and notes.
 */
class EnquiryPolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewEnquiries,
            'view' => Permission::ViewEnquiries,
            'update' => Permission::UpdateEnquiries,
            'delete' => Permission::DeleteEnquiries,
        ];
    }
}
