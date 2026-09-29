<?php

namespace App\Policies;

use App\Auth\Permission;

/**
 * The refresh history. Running a refresh is "create"; a finished import is
 * a record of what happened, so nobody edits or deletes one.
 */
class PerfumeImportPolicy extends BasePolicy
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
        ];
    }
}
