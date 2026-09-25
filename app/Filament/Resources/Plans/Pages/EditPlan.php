<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Filament\Resources\Plans\PlanResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

/**
 * A plan and its prices. No delete action: a plan is retired by switching it
 * off (see PlanPolicy).
 */
class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->syncAction(),
        ];
    }

    public function syncAction(): Action
    {
        return PlanResource::syncAction()->record($this->getRecord());
    }
}
