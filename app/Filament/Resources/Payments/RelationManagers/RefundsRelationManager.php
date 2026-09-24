<?php

namespace App\Filament\Resources\Payments\RelationManagers;

use App\Models\Refund;
use App\Payments\Enums\RefundStatus;
use App\Settings\Settings;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Refunds requested on the payment, with their outcome. Read-only: refunds are
 * issued with the Refund action, never edited.
 */
class RefundsRelationManager extends RelationManager
{
    protected static string $relationship = 'refunds';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Refunds');
    }

    public function table(Table $table): Table
    {
        $settings = app(Settings::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('initiator'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('payments.fields.created'))
                    ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state)),
                TextColumn::make('amount')
                    ->label(__('payments.fields.amount'))
                    ->state(fn (Refund $record): string => $record->money()->format()),
                TextColumn::make('status')
                    ->label(__('payments.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (RefundStatus $state): string => $state->label())
                    ->color(fn (RefundStatus $state): string => $state->color())
                    ->description(fn (Refund $record): ?string => $record->failure_reason),
                TextColumn::make('reason')
                    ->label(__('payments.fields.reason'))
                    ->placeholder('—')
                    ->limit(40),
                TextColumn::make('initiator.name')
                    ->label(__('payments.fields.initiated_by'))
                    ->placeholder(__('payments.fields.initiated_by_gateway')),
            ])
            ->defaultSort('id');
    }
}
