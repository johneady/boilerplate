<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for reading contact form submissions.
 *
 * There is deliberately no `create` permission. Submissions are created by
 * members of the public through App\Livewire\Contact, which authorizes nobody --
 * that is what a contact form is. Nothing in the panel creates one, so the
 * ability is left unmapped and BasePolicy denies it.
 *
 * `update` guards one thing only: marking a submission handled. The panel offers
 * no way to edit what somebody actually sent, and it should not -- the row is a
 * record of a message received.
 */
class ContactSubmissionPolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewContactSubmissions,
            'view' => Permission::ViewContactSubmissions,
            'update' => Permission::UpdateContactSubmissions,
            'delete' => Permission::DeleteContactSubmissions,
        ];
    }
}
