<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * Perfume data is curated by anyone holding the perfumes permission: the
 * owner and the data curator (editor) role.
 */
class PerfumePolicy extends BasePolicy
{
    /**
     * The permission required for each ability.
     *
     * @return array<string, Permission>
     */
    protected function permissions(): array
    {
        return [
            'viewAny' => Permission::ManagePerfumes,
            'view' => Permission::ManagePerfumes,
            'create' => Permission::ManagePerfumes,
            'update' => Permission::ManagePerfumes,
            'delete' => Permission::ManagePerfumes,
        ];
    }
}
