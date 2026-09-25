<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;

/**
 * The payments index. No create action: payments are taken at checkout or
 * recorded against a payable, never created from here.
 */
class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}
