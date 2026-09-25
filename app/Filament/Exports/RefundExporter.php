<?php

namespace App\Filament\Exports;

use App\Models\Refund;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\RefundStatus;
use App\Settings\Settings;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Exports\ExportColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Builder;

/**
 * Refunds for the bookkeeper, each beside the receipt it reverses.
 *
 * The tax refunded is its own column: a refund returns tax the business
 * already collected, which its tax return has to net off.
 */
class RefundExporter extends SpreadsheetExporter
{
    protected static ?string $model = Refund::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')
                ->label(__('exports.refunds.refunded_on'))
                ->state(fn (Refund $record): string => static::date($record->created_at)),
            ExportColumn::make('payment.receipt_number')
                ->label(__('exports.refunds.receipt_number'))
                ->state(fn (Refund $record): string => $record->payment?->receiptNumber() ?? ''),
            ExportColumn::make('payment.customer_name')
                ->label(__('exports.refunds.customer_name'))
                ->preventFormulaInjection(),
            ExportColumn::make('payment.description')
                ->label(__('exports.refunds.description'))
                ->preventFormulaInjection(),
            ExportColumn::make('currency')
                ->label(__('exports.refunds.currency'))
                ->state(fn (Refund $record): string => $record->currency->value),
            ExportColumn::make('amount')
                ->label(__('exports.refunds.amount'))
                ->state(fn (Refund $record): string => static::amount($record->money())),
            ExportColumn::make('tax_amount')
                ->label(__('exports.refunds.tax_amount'))
                ->state(fn (Refund $record): string => static::amount($record->taxMoney())),
            ExportColumn::make('status')
                ->label(__('exports.refunds.status'))
                ->formatStateUsing(fn (RefundStatus $state): string => __($state->label())),
            ExportColumn::make('reason')
                ->label(__('exports.refunds.reason'))
                ->preventFormulaInjection(),
            ExportColumn::make('payment.uuid')
                ->label(__('exports.refunds.payment_reference')),
        ];
    }

    /**
     * The date range, offered in the export modal.
     *
     * @return array<Component|Action|ActionGroup>
     */
    public static function getOptionsFormComponents(): array
    {
        return [
            DatePicker::make('refunded_from')->label(__('exports.refunds.from')),
            DatePicker::make('refunded_until')->label(__('exports.refunds.until')),
        ];
    }

    /**
     * Refunds that went through in one payments mode, made between two
     * business-calendar days.
     *
     * Succeeded only, as the receipt and refundedMoney() count them: a
     * declined attempt and its successful retry must not both be netted off.
     *
     * @return Builder<Refund>
     */
    public static function refundsFor(GatewayMode $mode, ?string $from, ?string $until): Builder
    {
        $settings = app(Settings::class);

        return Refund::query()
            ->with('payment')
            ->where('status', RefundStatus::Succeeded->value)
            ->whereHas('payment', fn (Builder $payment) => $payment->where('mode', $mode))
            ->when(filled($from), fn (Builder $query) => $query->where('created_at', '>=', $settings->startOfBusinessDay((string) $from)))
            ->when(filled($until), fn (Builder $query) => $query->where('created_at', '<=', $settings->endOfBusinessDay((string) $until)));
    }
}
