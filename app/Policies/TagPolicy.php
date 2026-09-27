<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for the blog's tags, guarded exactly as categories are --
 * see CategoryPolicy for why the post permissions carry this too.
 */
class TagPolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewPosts,
            'view' => Permission::ViewPosts,
            'create' => Permission::UpdatePosts,
            'update' => Permission::UpdatePosts,
            'delete' => Permission::UpdatePosts,
        ];
    }
}
