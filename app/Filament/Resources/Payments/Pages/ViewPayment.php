<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentActions;
use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * One payment, its ledger and its refunds, with the actions that move money.
 */
class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PaymentActions::refund(),
            PaymentActions::capture(),
            PaymentActions::void(),
        ];
    }
}
