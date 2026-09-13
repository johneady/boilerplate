<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use Filament\Resources\Pages\ListRecords;

/**
 * ListRecords rather than ManageRecords: there is no create or edit form.
 *
 * ManageRecords would render a "New file" button that MediaPolicy denies --
 * uploading goes through App\Media\MediaManager, never a panel form. See the
 * resource's docblock.
 */
class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;
}
