<?php

namespace App\Filament\Exports;

use App\Models\Payment;
use App\Models\TaxRate;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Money;
use App\Payments\Tax\TaxLine;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * Payments for the bookkeeper: one row per payment, one column per tax.
 *
 * Exports the paid payments the table is showing -- its mode, status and
 * date filters -- so "last month's live payments" is a filter and a click.
 */
class PaymentExporter extends SpreadsheetExporter
{
    protected static ?string $model = Payment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('receipt_number')
                ->label(__('exports.payments.receipt_number'))
                ->state(fn (Payment $record): string => $record->receiptNumber() ?? ''),
            ExportColumn::make('paid_at')
                ->label(__('exports.payments.paid_on'))
                // A manual payment is dated by when the money arrived.
                ->state(fn (Payment $record): string => $record->manual_received_on !== null
                    ? static::calendarDate($record->manual_received_on)
                    : static::date($record->paid_at)),
            ExportColumn::make('customer_name')
                ->label(__('exports.payments.customer_name'))
                ->preventFormulaInjection(),
            ExportColumn::make('customer_email')
                ->label(__('exports.payments.customer_email'))
                ->preventFormulaInjection(),
            ExportColumn::make('description')
                ->label(__('exports.payments.description'))
                ->preventFormulaInjection(),
            ExportColumn::make('status')
                ->label(__('exports.payments.status'))
                ->formatStateUsing(fn (PaymentStatus $state): string => __($state->label())),
            ExportColumn::make('gateway')
                ->label(__('exports.payments.method'))
                ->state(fn (Payment $record): string => $record->manual_method !== null
                    ? __($record->manual_method->label())
                    : __($record->gateway->label())),
            ExportColumn::make('manual_reference')
                ->label(__('exports.payments.method_reference'))
                ->preventFormulaInjection(),
            ExportColumn::make('mode')
                ->label(__('exports.payments.mode'))
                ->formatStateUsing(fn (GatewayMode $state): string => __($state->label())),
            ExportColumn::make('currency')
                ->label(__('exports.payments.currency'))
                ->state(fn (Payment $record): string => $record->currency->value),
            ExportColumn::make('subtotal')
                ->label(__('exports.payments.subtotal'))
                ->state(fn (Payment $record): string => static::amount($record->subtotalMoney())),
            ...static::taxColumns(),
            ExportColumn::make('tax_total')
                ->label(__('exports.payments.tax_total'))
                ->state(fn (Payment $record): string => static::amount($record->taxTotalMoney())),
            ExportColumn::make('amount')
                ->label(__('exports.payments.total'))
                ->state(fn (Payment $record): string => static::amount($record->total())),
            ExportColumn::make('amount_captured')
                ->label(__('exports.payments.paid'))
                ->state(fn (Payment $record): string => $record->status->isPaid() ? static::amount($record->paidMoney()) : '0.00'),
            ExportColumn::make('amount_refunded')
                ->label(__('exports.payments.refunded'))
                ->state(fn (Payment $record): string => static::amount($record->refundedMoney())),
            ExportColumn::make('net')
                ->label(__('exports.payments.net'))
                ->state(fn (Payment $record): string => $record->status->isPaid()
                    ? static::amount($record->paidMoney()->subtract($record->refundedMoney()))
                    : '0.00'),
            ExportColumn::make('uuid')
                ->label(__('exports.payments.reference')),
            ExportColumn::make('gateway_payment_id')
                ->label(__('exports.payments.gateway_reference'))
                ->enabledByDefault(false),
            ExportColumn::make('created_at')
                ->label(__('exports.payments.created_at'))
                ->state(fn (Payment $record): string => static::dateTime($record->created_at))
                ->enabledByDefault(false),
        ];
    }

    /**
     * Only payments that were paid: a checkout abandoned or declined still
     * holds the tax it would have charged, and summing a tax column must
     * give the tax actually collected. A payment keeps its receipt number
     * after a refund, so refunded ones stay in, with their refunds beside.
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->whereNotNull('receipt_number');
    }

    /**
     * One column per configured tax, holding what each payment was charged.
     *
     * Matched to the payment's snapshotted lines by name, which is what a
     * receipt prints. A tax since deleted has no column of its own, but still
     * counts in the tax total beside these.
     *
     * @return list<ExportColumn>
     */
    protected static function taxColumns(): array
    {
        return array_values(TaxRate::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->unique('name')
            ->map(fn (TaxRate $rate): ExportColumn => ExportColumn::make("tax_{$rate->id}")
                ->label($rate->name)
                ->state(fn (Payment $record): string => static::amount(array_reduce(
                    array_filter($record->taxLines(), fn (TaxLine $line): bool => $line->name === $rate->name),
                    fn (Money $sum, TaxLine $line): Money => $sum->add($line->amount),
                    Money::zero($record->currency),
                ))))
            ->all());
    }
}
