<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for the blog's categories.
 *
 * Guarded by the post permissions rather than a set of its own: a category
 * is a grouping of posts, not a thing that exists apart from them, and
 * deciding who may rename one is not a decision a boilerplate should hand an
 * administrator. Reading follows ViewPosts; changing follows UpdatePosts,
 * which is also what lets its holder preview an unpublished post -- the
 * same trust level a category edit is.
 */
class CategoryPolicy extends BasePolicy
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
