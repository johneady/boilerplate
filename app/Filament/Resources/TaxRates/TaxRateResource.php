<?php

namespace App\Filament\Resources\TaxRates;

use App\Filament\Resources\TaxRates\Pages\ManageTaxRates;
use App\Models\TaxRate;
use App\Payments\PaymentManager;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The taxes added to taxable items, in the order they appear on a receipt.
 *
 * Every active rate applies to every taxable item. Editing or deleting a rate
 * never changes a past payment, which recorded the rates it was charged.
 */
class TaxRateResource extends Resource
{
    protected static ?string $model = TaxRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('payments.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('payments.tax_rate.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payments.tax_rate.plural_label');
    }

    public static function canAccess(): bool
    {
        return app(PaymentManager::class)->enabled() && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('payments.tax_rates.name'))
                    ->helperText(__('payments.tax_rates.name_help'))
                    ->required()
                    ->maxLength(50),
                TextInput::make('percentage')
                    ->label(__('payments.tax_rates.percentage'))
                    ->helperText(__('payments.tax_rates.percentage_help'))
                    ->required()
                    ->inputMode('decimal')
                    ->rule('regex:/^\d{1,2}(\.\d{1,3})?$|^100(\.0{1,3})?$/')
                    ->suffix('%'),
                Toggle::make('is_active')
                    ->label(__('payments.tax_rates.is_active'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('payments.tax_rates.name')),
                TextColumn::make('percentage')
                    ->label(__('payments.tax_rates.percentage'))
                    ->state(fn (TaxRate $record): string => $record->label()),
                IconColumn::make('is_active')
                    ->label(__('payments.tax_rates.is_active'))
                    ->boolean(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->emptyStateDescription(__('payments.tax_rates.none_active'))
            ->recordActions([
                EditAction::make(),
                // Shown, disabled, on a rate the gateways hold a copy of, so the
                // administrator learns to switch it off instead (TaxRatePolicy).
                DeleteAction::make()
                    ->authorizationTooltip(fn (TaxRate $record): bool => filled($record->gateway_refs))
                    ->authorizationMessage(__('payments.tax_rates.delete_synced')),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTaxRates::route('/'),
        ];
    }
}
