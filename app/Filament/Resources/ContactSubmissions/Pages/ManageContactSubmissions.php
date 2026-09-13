<?php

namespace App\Filament\Resources\ContactSubmissions\Pages;

use App\Filament\Resources\ContactSubmissions\ContactSubmissionResource;
use Filament\Resources\Pages\ManageRecords;

/**
 * The contact form inbox.
 *
 * No CreateAction header: submissions arrive from the public form, and the panel
 * has no business manufacturing one.
 */
class ManageContactSubmissions extends ManageRecords
{
    protected static string $resource = ContactSubmissionResource::class;
}
