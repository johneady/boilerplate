<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentActions;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Payments\ReceiptPdf;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One payment, its ledger and its refunds, with the actions that move money.
 */
class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->downloadReceiptAction(),
            PaymentActions::refund(),
            PaymentActions::capture(),
            PaymentActions::void(),
        ];
    }

    /**
     * The customer's PDF receipt, for staff answering "can you resend it?".
     *
     * Anyone who may view the payment may download it: it holds nothing the
     * payment page does not already show them.
     */
    private function downloadReceiptAction(): Action
    {
        return Action::make('downloadReceipt')
            ->label(__('payments.actions.download_receipt'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn (Payment $record): bool => ReceiptPdf::availableFor($record))
            ->authorize('view')
            ->action(fn (Payment $record, ReceiptPdf $receipt): StreamedResponse => $receipt->download($record));
    }
}
