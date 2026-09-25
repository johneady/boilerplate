<?php

namespace App\Filament\Resources\WebhookEvents\Pages;

use App\Filament\Resources\WebhookEvents\WebhookEventResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Received webhook events. ListRecords, not ManageRecords: nobody creates or
 * edits one.
 */
class ListWebhookEvents extends ListRecords
{
    protected static string $resource = WebhookEventResource::class;
}
