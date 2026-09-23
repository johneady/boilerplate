<?php

namespace App\Filament\Widgets;

use App\Bakery\InquiryStatus;
use App\Filament\Resources\OrderInquiries\OrderInquiryResource;
use App\Models\OrderInquiry;
use App\Settings\Settings;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The next two weeks of quoted and confirmed orders, soonest first -- the list
 * a home baker plans their shopping and oven time from.
 */
class BakingSchedule extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $settings = app(Settings::class);

        return $table
            ->heading(__('bakery.dashboard.upcoming_heading'))
            ->description(__('bakery.dashboard.upcoming_description'))
            ->query(fn (): Builder => OrderInquiry::query()
                ->whereIn('status', [InquiryStatus::Quoted, InquiryStatus::Confirmed])
                ->whereDate('needed_on', '>=', today())
                ->whereDate('needed_on', '<=', today()->addDays(14))
                ->orderBy('needed_on'))
            ->columns([
                TextColumn::make('needed_on')
                    ->label(__('bakery.inquiries.fields.needed_on'))
                    ->formatStateUsing(fn ($state): string => $settings->formatDate($state))
                    ->description(fn (OrderInquiry $record): string => __($record->fulfilment->label())),
                TextColumn::make('items')
                    ->label(__('bakery.inquiries.fields.items'))
                    ->state(fn (OrderInquiry $record): string => $record->itemSummary())
                    ->wrap(),
                TextColumn::make('name')
                    ->label(__('bakery.inquiries.fields.customer')),
                TextColumn::make('status')
                    ->label(__('bakery.inquiries.fields.status'))
                    ->formatStateUsing(fn (InquiryStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (InquiryStatus $state): string => $state->color()),
            ])
            ->recordUrl(fn (OrderInquiry $record): string => OrderInquiryResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('bakery.dashboard.upcoming_empty'))
            ->paginated(false);
    }
}
