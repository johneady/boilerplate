<?php

namespace App\Filament\Resources\Disputes\Pages;

use App\Filament\Resources\Disputes\DisputeResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewDispute extends ViewRecord
{
    protected static string $resource = DisputeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->respondAction(),
        ];
    }

    public function respondAction(): Action
    {
        return DisputeResource::respondAction();
    }
}
