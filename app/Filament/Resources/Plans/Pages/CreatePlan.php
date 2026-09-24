<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Filament\Resources\Plans\PlanResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * A new plan. Its prices are added on the edit page it opens on.
 */
class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected function getRedirectUrl(): string
    {
        return PlanResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
