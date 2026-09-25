<?php

namespace App\Filament\Resources\PaymentLinks\Pages;

use App\Filament\Resources\PaymentLinks\PaymentLinkResource;
use App\Models\PaymentLink;
use App\Models\User;
use App\Payments\PaymentManager;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

/**
 * The payment links, created and edited in modals.
 */
class ManagePaymentLinks extends ManageRecords
{
    protected static string $resource = PaymentLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // The currency is the installation's at the moment the link is
                // made, and stays with the link; created_by is not
                // mass-assignable, so it is set here rather than trusted from
                // the form.
                ->using(function (array $data): Model {
                    $link = new PaymentLink($data);
                    $link->currency = app(PaymentManager::class)->currency();
                    $user = auth()->user();
                    $link->created_by = $user instanceof User ? $user->id : null;
                    $link->save();

                    return $link;
                }),
        ];
    }
}
