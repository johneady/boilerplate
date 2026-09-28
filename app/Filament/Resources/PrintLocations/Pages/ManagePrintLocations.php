<?php

namespace App\Filament\Resources\PrintLocations\Pages;

use App\Filament\Resources\PrintLocations\PrintLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePrintLocations extends ManageRecords
{
    protected static string $resource = PrintLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false),
        ];
    }
}
