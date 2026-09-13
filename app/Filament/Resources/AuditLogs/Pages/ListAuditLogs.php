<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

/**
 * The audit trail index.
 *
 * ListRecords rather than ManageRecords, which is what the other resources in
 * this panel use: ManageRecords brings a create action and a modal edit form
 * with it, and there is no such thing as creating or editing an audit entry.
 *
 * getHeaderActions() is deliberately not overridden -- there are none.
 */
class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;
}
