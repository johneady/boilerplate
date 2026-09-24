<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Filament\Resources\Subscriptions\SubscriptionActions;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/**
 * One subscription and its payments, with the actions that change it at the
 * gateway.
 */
class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->refreshAction(),
            $this->cancelAtPeriodEndAction(),
            $this->resumeAction(),
            $this->cancelNowAction(),
            $this->simulateRenewalAction(),
            $this->simulateFailedRenewalAction(),
        ];
    }

    public function refreshAction(): Action
    {
        return SubscriptionActions::refresh();
    }

    public function cancelAtPeriodEndAction(): Action
    {
        return SubscriptionActions::cancelAtPeriodEnd();
    }

    public function resumeAction(): Action
    {
        return SubscriptionActions::resume();
    }

    public function cancelNowAction(): Action
    {
        return SubscriptionActions::cancelNow();
    }

    public function simulateRenewalAction(): Action
    {
        return SubscriptionActions::simulateRenewal();
    }

    public function simulateFailedRenewalAction(): Action
    {
        return SubscriptionActions::simulateFailedRenewal();
    }
}
