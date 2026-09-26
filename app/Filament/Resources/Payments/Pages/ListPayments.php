<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Exports\RefundExporter;
use App\Filament\Resources\Payments\PaymentResource;
use App\Payments\PaymentManager;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

/**
 * The payments index. No create action: payments are taken at checkout or
 * recorded against a payable, never created from here.
 */
class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->exportRefundsAction(),
        ];
    }

    /**
     * Refunds have no list of their own -- each sits under its payment -- so
     * their export lives here, for the current payments mode, with the date
     * range the exporter asks for.
     *
     * The query is replaced, not refined: on a page with a table, Filament
     * starts every export from the table's query, which here is payments.
     */
    private function exportRefundsAction(): ExportAction
    {
        return ExportAction::make('exportRefunds')
            ->label(__('exports.export_refunds'))
            ->color('gray')
            ->exporter(RefundExporter::class)
            ->modifyQueryUsing(fn (array $options): Builder => RefundExporter::refundsFor(
                app(PaymentManager::class)->mode(),
                $options['refunded_from'] ?? null,
                $options['refunded_until'] ?? null,
            ));
    }
}
