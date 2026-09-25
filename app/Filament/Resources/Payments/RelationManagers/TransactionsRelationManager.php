<?php

namespace App\Filament\Resources\Payments\RelationManagers;

use App\Models\PaymentTransaction;
use App\Payments\Enums\TransactionSource;
use App\Payments\Enums\TransactionType;
use App\Settings\Settings;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The payment's ledger: every movement of money, in order. Read-only.
 */
class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('payments.ledger');
    }

    public function table(Table $table): Table
    {
        $settings = app(Settings::class);

        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label(__('payments.fields.occurred'))
                    ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state)),
                TextColumn::make('type')
                    ->label(__('payments.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (TransactionType $state): string => __($state->label()))
                    ->color(fn (TransactionType $state): string => $state->color()),
                TextColumn::make('amount')
                    ->label(__('payments.fields.amount'))
                    ->state(fn (PaymentTransaction $record): string => $record->money()->format()),
                TextColumn::make('source')
                    ->label(__('payments.fields.source'))
                    ->formatStateUsing(fn (TransactionSource $state): string => __($state->label())),
                TextColumn::make('gateway_transaction_id')
                    ->label(__('payments.fields.transaction_id'))
                    ->fontFamily('mono')
                    ->copyable(),
            ])
            ->defaultSort('id');
    }
}
