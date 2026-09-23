<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Models\Enquiry;
use App\Voltiva\EnquiryStatus;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewEnquiry extends ViewRecord
{
    protected static string $resource = EnquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('update')
                ->label(__('voltiva.enquiries.actions.update'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->authorize(fn (): bool => auth()->user()?->can('update', $this->getRecord()) ?? false)
                ->fillForm(fn (Enquiry $record): array => [
                    'status' => $record->status->value,
                    'staff_notes' => $record->staff_notes,
                ])
                ->schema([
                    Select::make('status')
                        ->label(__('voltiva.enquiries.fields.status'))
                        ->options(EnquiryResource::statusOptions())
                        ->required(),
                    Textarea::make('staff_notes')
                        ->label(__('voltiva.enquiries.fields.staff_notes'))
                        ->rows(5),
                ])
                ->action(function (Enquiry $record, array $data): void {
                    $record->status = EnquiryStatus::from($data['status']);
                    $record->staff_notes = $data['staff_notes'] ?: null;

                    // Closing an enquiry ends its email sequence at once, so
                    // the timeline shows "stopped" rather than a date.
                    if (! $record->status->receivesFollowUps()) {
                        $record->next_follow_up_at = null;
                    }

                    $record->save();

                    Notification::make()->success()->title(__('voltiva.enquiries.updated'))->send();
                }),
            Action::make('stopEmails')
                ->label(__('voltiva.enquiries.actions.stop_emails'))
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (Enquiry $record): bool => $record->next_follow_up_at !== null)
                ->authorize(fn (): bool => auth()->user()?->can('update', $this->getRecord()) ?? false)
                ->action(fn (Enquiry $record) => $record->forceFill(['next_follow_up_at' => null])->save()),
            DeleteAction::make(),
        ];
    }
}
