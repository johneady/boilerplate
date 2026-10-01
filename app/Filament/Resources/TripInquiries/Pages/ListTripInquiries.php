<?php

namespace App\Filament\Resources\TripInquiries\Pages;

use App\Filament\Resources\TripInquiries\TripInquiryResource;
use App\Travel\InquiryStatus;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * The inbox, tabbed by where requests stand. No create action: requests come
 * from the public site.
 */
class ListTripInquiries extends ListRecords
{
    protected static string $resource = TripInquiryResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'open' => Tab::make(__('Open'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [InquiryStatus::New, InquiryStatus::Contacted])),
            'confirmed' => Tab::make(__('Confirmed'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InquiryStatus::Confirmed)),
            'all' => Tab::make(__('All')),
        ];
    }
}
