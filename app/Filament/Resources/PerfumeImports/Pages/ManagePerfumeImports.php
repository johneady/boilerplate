<?php

namespace App\Filament\Resources\PerfumeImports\Pages;

use App\Filament\Resources\PerfumeImports\PerfumeImportResource;
use App\Models\PerfumeImport;
use App\Perfumes\Enums\ImportStatus;
use App\Perfumes\PerfumeImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManagePerfumeImports extends ManageRecords
{
    protected static string $resource = PerfumeImportResource::class;

    public function getSubheading(): ?string
    {
        return __('perfumes.imports.scheduled', [
            'state' => filled(config('perfumes.feed_url'))
                ? __('perfumes.imports.scheduled_on')
                : __('perfumes.imports.scheduled_off'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sample')
                ->label(__('perfumes.imports.actions.sample'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): BinaryFileResponse => response()->download(
                    database_path('seeders/data/sample-refresh.csv'),
                    'sillage-sample-refresh.csv',
                    ['Content-Type' => 'text/csv'],
                )),
            Action::make('run')
                ->label(__('perfumes.imports.actions.run'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->authorize('create', PerfumeImport::class)
                ->modalSubmitActionLabel(__('perfumes.imports.actions.run_submit'))
                ->modalDescription(__('perfumes.imports.description'))
                ->schema([
                    FileUpload::make('file')
                        ->label(__('perfumes.imports.fields.file'))
                        ->helperText(__('perfumes.imports.fields.file_help'))
                        ->disk('local')
                        ->directory('perfume-imports')
                        ->storeFileNamesIn('original_name')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->maxSize(20 * 1024)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $disk = Storage::disk('local');

                    try {
                        $import = app(PerfumeImporter::class)->import(
                            $disk->path($data['file']),
                            (string) ($data['original_name'] ?? basename($data['file'])),
                            auth()->user(),
                        );
                    } finally {
                        $disk->delete($data['file']);
                    }

                    $this->notifyAbout($import);
                }),
        ];
    }

    private function notifyAbout(PerfumeImport $import): void
    {
        if ($import->status === ImportStatus::Failed) {
            Notification::make()
                ->danger()
                ->title(__('perfumes.imports.notifications.failed'))
                ->body(collect($import->errors)->implode(' '))
                ->send();

            return;
        }

        Notification::make()
            ->status($import->rows_failed > 0 ? 'warning' : 'success')
            ->title(__('perfumes.imports.notifications.finished', [
                'created' => $import->rows_created,
                'updated' => $import->rows_updated,
                'unchanged' => $import->rows_unchanged,
            ]))
            ->body($import->rows_failed > 0 ? $this->failedRowsMessage($import->rows_failed) : null)
            ->send();
    }

    /**
     * __() widens to string|array once replacements are passed; the array
     * arm is unreachable for this string key (.ai/rules/i18n.md).
     */
    private function failedRowsMessage(int $count): string
    {
        return (string) __('perfumes.imports.notifications.failed_rows', ['count' => $count]);
    }
}
