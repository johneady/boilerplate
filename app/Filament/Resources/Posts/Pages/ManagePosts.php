<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePosts extends ManageRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                // The author is whoever writes the post, never chosen: it
                // decides who may edit it afterwards (see PostPolicy).
                ->mutateDataUsing(function (array $data): array {
                    $data['author_id'] = auth()->id();

                    return $data;
                }),
        ];
    }
}
