<?php

namespace App\Filament\Resources\Payments\RelationManagers;

use App\Filament\Resources\Disputes\DisputeResource;
use App\Models\Dispute;
use App\Payments\Enums\DisputeStatus;
use App\Settings\Settings;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Chargebacks and PayPal claims against the payment. Read-only.
 */
class DisputesRelationManager extends RelationManager
{
    protected static string $relationship = 'disputes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('payments.dispute.plural_label');
    }

    public function table(Table $table): Table
    {
        $settings = app(Settings::class);

        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('payments.disputes.opened'))
                    ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state)),
                TextColumn::make('amount')
                    ->label(__('payments.fields.amount'))
                    ->state(fn (Dispute $record): string => $record->money()->format()),
                TextColumn::make('status')
                    ->label(__('payments.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (DisputeStatus $state): string => __($state->label()))
                    ->color(fn (DisputeStatus $state): string => $state->color()),
                TextColumn::make('evidence_due_by')
                    ->label(__('payments.disputes.evidence_due_by'))
                    ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state))
                    ->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('open')
                    ->label(__('payments.subscriptions.open_payment'))
                    ->url(fn (Dispute $record): string => DisputeResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
