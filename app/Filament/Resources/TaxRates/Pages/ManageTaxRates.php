<?php

namespace App\Filament\Resources\TaxRates\Pages;

use App\Filament\Resources\TaxRates\TaxRateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTaxRates extends ManageRecords
{
    protected static string $resource = TaxRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
