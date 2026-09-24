<?php

namespace App\Filament\Resources\Subscriptions\RelationManagers;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Payments\Enums\PaymentStatus;
use App\Settings\Settings;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The payments the gateway has taken for the subscription. Each is an
 * ordinary payment: refunds are made from its own page.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('payments.payment.plural_label');
    }

    public function table(Table $table): Table
    {
        $settings = app(Settings::class);

        return $table
            ->columns([
                TextColumn::make('paid_at')
                    ->label(__('payments.fields.paid_at'))
                    ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state))
                    ->placeholder('—'),
                TextColumn::make('amount')
                    ->label(__('payments.fields.total'))
                    ->state(fn (Payment $record): string => $record->total()->format()),
                TextColumn::make('status')
                    ->label(__('payments.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color()),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('open')
                    ->label(__('payments.subscriptions.open_payment'))
                    ->url(fn (Payment $record): string => PaymentResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
