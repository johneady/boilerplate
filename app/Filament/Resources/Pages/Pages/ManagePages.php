<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\View\View;

class ManagePages extends ManageRecords
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false),
        ];
    }

    /**
     * The note below the table explaining what dragging a row changes.
     *
     * Rendered here rather than through a panel-wide PAGE_END render hook, so
     * it cannot leak onto every other page in the panel.
     */
    public function getFooter(): ?View
    {
        return view('filament.resources.pages.reorder-note');
    }
}
