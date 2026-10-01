<?php

namespace App\Filament\Resources\TripInquiries\Pages;

use App\Filament\Resources\TripInquiries\TripInquiryResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTripInquiry extends ViewRecord
{
    protected static string $resource = TripInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return TripInquiryResource::statusActions();
    }
}
