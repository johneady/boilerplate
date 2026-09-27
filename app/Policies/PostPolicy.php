<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\Post;
use App\Models\User;

/**
 * Authorization for blog posts.
 *
 * Posts are owned by their author: the update and delete permissions reach
 * only the holder's own posts, and ManageAnyPost (managers; administrators
 * pass the Gate::before bypass) lifts that
 * to every post -- including one whose author has since been deleted, which
 * nobody else can then touch. Checked against the class rather than a
 * record, the ownership half does not apply, so the panel still offers the
 * edit and bulk-delete controls that each record then answers for.
 *
 * The `update` ability does double duty: besides the panel's edit form it is
 * what the blog's post controller checks to decide whether a draft or a
 * scheduled post may be previewed by URL. Someone who may edit one may see
 * it; everyone else gets the 404 a guest would.
 */
class PostPolicy extends BasePolicy
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
            'create' => Permission::CreatePosts,
            'update' => Permission::UpdatePosts,
            'delete' => Permission::DeletePosts,
        ];
    }

    /**
     * Determine whether the user may update this post.
     */
    public function update(User $user, mixed $model = null): bool
    {
        return parent::update($user, $model) && $this->owns($user, $model);
    }

    /**
     * Determine whether the user may delete this post.
     */
    public function delete(User $user, mixed $model = null): bool
    {
        return parent::delete($user, $model) && $this->owns($user, $model);
    }

    /**
     * Whether the post is the user's own to manage.
     */
    private function owns(User $user, mixed $model): bool
    {
        if (! $model instanceof Post) {
            return true;
        }

        return $model->author_id === $user->id
            || $user->hasPermission(Permission::ManageAnyPost);
    }
}
