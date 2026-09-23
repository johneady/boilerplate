<?php

namespace App\Filament\Resources\OrderInquiries\Pages;

use App\Bakery\InquiryStatus;
use App\Filament\Resources\OrderInquiries\OrderInquiryResource;
use App\Models\OrderInquiry;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * The order book, split the way a baker works it: what needs a reply, what is
 * coming up (soonest first), and what is done.
 */
class ListOrderInquiries extends ListRecords
{
    protected static string $resource = OrderInquiryResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('bakery.inquiries.tabs.all')),
            'new' => Tab::make(__('bakery.inquiries.tabs.new'))
                ->badge(fn (): int => OrderInquiry::query()->awaitingReply()->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InquiryStatus::New)),
            'upcoming' => Tab::make(__('bakery.inquiries.tabs.upcoming'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereIn('status', [InquiryStatus::Quoted, InquiryStatus::Confirmed])
                    ->whereDate('needed_on', '>=', today())
                    ->reorder('needed_on')),
            'past' => Tab::make(__('bakery.inquiries.tabs.past'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [InquiryStatus::Completed, InquiryStatus::Declined])),
        ];
    }
}
