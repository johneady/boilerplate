<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for managing the public content pages.
 *
 * The `update` permission does double duty: besides the panel's edit form it is
 * what App\Http\Controllers\PageController checks to decide whether an
 * unpublished page may be previewed by URL. Someone who may edit a draft may
 * see it; everyone else gets the 404 a guest would.
 */
class PagePolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * No restore or forceDelete: pages are not soft-deleted, so those abilities
     * deny everyone through BasePolicy's default.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewPages,
            'view' => Permission::ViewPages,
            'create' => Permission::CreatePages,
            'update' => Permission::UpdatePages,
            'delete' => Permission::DeletePages,
        ];
    }
}
