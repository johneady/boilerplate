<?php

namespace App\Filament\Resources\Perfumes\Pages;

use App\Filament\Resources\Perfumes\PerfumeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManagePerfumes extends ManageRecords
{
    protected static string $resource = PerfumeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(Width::ThreeExtraLarge)
                ->createAnother(false),
        ];
    }
}
