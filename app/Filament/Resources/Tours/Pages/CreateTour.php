<?php

namespace App\Filament\Resources\Tours\Pages;

use App\Filament\Resources\Tours\TourResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    protected static string $resource = TourResource::class;

    /**
     * Straight to the edit page, where the departure dates are added.
     */
    protected function getRedirectUrl(): string
    {
        return TourResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
