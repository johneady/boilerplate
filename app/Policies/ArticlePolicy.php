<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Authorization for managing News & Advice articles in the admin panel.
 */
class ArticlePolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ViewArticles,
            'view' => Permission::ViewArticles,
            'create' => Permission::CreateArticles,
            'update' => Permission::UpdateArticles,
            'delete' => Permission::DeleteArticles,
        ];
    }
}
